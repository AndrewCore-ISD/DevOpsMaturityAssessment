<?php

/* Copyright 2018 Atos SE and Worldline
 * Licensed under MIT (https://github.com/atosorigin/DevOpsMaturityAssessment/blob/master/LICENSE)
 
 */

session_name('devopsassessment');
session_start();

Class Survey
{
	public $sections;

	public function __construct() 
	{
		// Load all the questions into session storage if we haven't already done so
		if (!isset($_SESSION['Sections'])) {
			$json = file_get_contents("questions.json");
			$_SESSION['Sections'] = json_decode($json, true);
		}
		$this->sections = &$_SESSION['Sections'];
		$this->SetupAnswerIDs(); // TODO: This should only be called first time we setup the Sections sesssion variable
		$this->SaveResponses();
	}
	
	function GUIDv4 ($trim = true)
	{
		// Windows
		if (function_exists('com_create_guid') === true) {
			if ($trim === true)
				return trim(com_create_guid(), '{}');
			else
				return com_create_guid();
		}

		// OSX/Linux
		if (function_exists('openssl_random_pseudo_bytes') === true) {
			$data = openssl_random_pseudo_bytes(16);
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
			return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		}

		// Fallback (PHP 4.2+)
		mt_srand((double)microtime() * 10000);
		$charid = strtolower(md5(uniqid(rand(), true)));
		$hyphen = chr(45);                  // "-"
		$lbrace = $trim ? "" : chr(123);    // "{"
		$rbrace = $trim ? "" : chr(125);    // "}"
		$guidv4 = $lbrace.
				substr($charid,  0,  8).$hyphen.
				substr($charid,  8,  4).$hyphen.
				substr($charid, 12,  4).$hyphen.
				substr($charid, 16,  4).$hyphen.
				substr($charid, 20, 12).
				$rbrace;
		return $guidv4;
	}

	public function SaveResults()
	{
		$servername = "localhost";
		$username = "writer";
		$password = "Password123";
		$dbName = "devops";

		$conn = new mysqli($servername, $username, $password, $dbName);

		if($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}

		if (!isset($_SESSION['guid'])) {
			$_SESSION['guid'] = $this->GUIDv4 ();
			$guid = $_SESSION['guid'];

			$surveyId = -1;

			$sql = "INSERT INTO `survey` (`SubmitDate`, `guid`) VALUES (NOW(), '" . $guid ."' )";
			if($conn->query($sql) == TRUE)
			{
				$sql = "SELECT * FROM survey WHERE guid = '" . $guid . "'";
				$result = $conn->query($sql);
				$surveyId = mysqli_fetch_assoc($result)['SurveyId'];
			}
			//save raw version for comparison
			$sql = "INSERT INTO `surveyraw`( `SurveyId`, `Raw`) VALUES (" . $surveyId . ", '" . mysqli_real_escape_string($conn, json_encode($this->sections)) . "');";
			$result = $conn->query($sql);

			//save to surveyanswers
			foreach ($this->sections as $section)
			{
				//get the sectionId
				$sectionName = $section['SectionName'];
				$sectionId = -1;
				$sql = "SELECT * FROM section WHERE Title = '" . $sectionName . "';";
				echo $sql;
				$result = $conn->query($sql);
				if (mysqli_num_rows($result) != 0)
				{
					$sectionId = mysqli_fetch_assoc($result)['SectionId'];
				}
				//if section doesn't exist, skip
				if ($sectionId == -1)
				{
					continue;
				}

				foreach ($section['Questions'] as $question)
				{
					//skip any non-question section
					if($question['Type'] == 'Banner')
					{
						continue;
					}
					else 
					{
						$questionText = $question['QuestionText'];
						$questionId = -1;
						$sql = "SELECT * FROM question WHERE Text = '" . mysqli_real_escape_string($conn, $questionText) . "' && SectionId = " . $sectionId . ";";
						$result = $conn->query($sql);
						if (mysqli_num_rows($result) != 0)
						{
							$questionId = mysqli_fetch_assoc($result)['QuestionId'];
						}
						if($questionId == -1)
						{
							continue;
						}
						//look through all the answers
						foreach($question['Answers'] as $answer)
						{
							if($answer['Value'] == "checked")
							{
								$answerText = $answer['Answer'];
								$answerId = -1;
								$sql = "SELECT * FROM questionanswer WHERE Text = '" . $answerText . "' && Questionid = " . $questionId . ";";
								$result = $conn->query($sql);
								if (mysqli_num_rows($result) != 0)
								{
									$answerId = mysqli_fetch_assoc($result)['QuestionAnswerId'];
								}
								if($answerId == -1)
								{
									continue;
								}
								//If found, insert to surveyanswer
								$sql = "INSERT INTO `surveyanswer`(`SurveyId`, `QuestionId`, `AnswerId`) VALUES (" . $surveyId . ", " . $questionId . ", " . $answerId . ");";
								$conn->query($sql);
							}
						}
					}
				}
			}
		}

		$conn->close(); 
	}

	public function GenerateResultsSummary()
	{
		foreach ($this->sections as $section)
		{
			$summaryResults[$section['SectionName']] = array('MaxScore'=>0, 'Score'=>0, 'ScorePercentage'=>0);
			if ( isset($section['SpiderPos']) )
			{
				$summaryResults[$section['SectionName']]['SpiderPos'] = $section['SpiderPos'];
			}
			
			foreach ($section['Questions'] as $question)
			{
				$summaryResults[$section['SectionName']]['MaxScore'] += $this->GetQuestionMaxScore($question); 
				$summaryResults[$section['SectionName']]['Score'] += $this->GetQuestionScore($question);
			}
			
			if ( $summaryResults[$section['SectionName']]['MaxScore'] != 0 )
			{
				$summaryResults[$section['SectionName']]['ScorePercentage'] = 
					round( $summaryResults[$section['SectionName']]['Score'] /
							$summaryResults[$section['SectionName']]['MaxScore'] * 100);
			}
			
			// Do not include sections where you cannot score (i.e. MaxScore == 0)
			if ( $summaryResults[$section['SectionName']]['MaxScore'] == 0 )
			{
				unset($summaryResults[$section['SectionName']]);
			}
		}
		
		return $summaryResults;
	}
	
	// returns summary results for the sub-categories in a specified section
	public function GenerateSubCategorySummary($sectionName)
	{
		foreach ($this->sections as $section)
		{
			if ( $section['SectionName'] == $sectionName )
			{
				foreach ($section['Questions'] as $question)
				{
					if ( isset($question['SubCategory']) )
					{
						if ( !isset($summaryResults[$question['SubCategory']]) )
						{
							// If we haven't yet added an entry into the summary results for this sub-category, then add one
							$summaryResults[$question['SubCategory']] = array('MaxScore'=>0, 'Score'=>0, 'ScorePercentage'=>0);
						}
						
						$summaryResults[$question['SubCategory']]['MaxScore'] += $this->GetQuestionMaxScore($question); 
						$summaryResults[$question['SubCategory']]['Score'] += $this->GetQuestionScore($question);
					}
				}
			}
		}
		
		foreach ($summaryResults as &$subCategory)
		{
			$subCategory['ScorePercentage'] = 
					round( $subCategory['Score'] / $subCategory['MaxScore'] * 100);	
		}
		
		return $summaryResults;
	}
	
	public function GetQuestionMaxScore($question)
	{
		$maxScore = 0;
		if ($question['Type'] != 'Banner')
		{
			foreach ($question['Answers'] as $answer)
			{
				if ($question['Type'] == 'Option')
				{
					if ($answer['Score'] > $maxScore)
					{
						$maxScore = $answer['Score'];
					}
				}
				if ($question['Type'] == 'Checkbox')
				{
					$maxScore += $answer['Score'];
				}
			}
		}
		
		return $maxScore;
	}

	public function GetQuestionScore($question)
	{
		$score = 0;
		if ($question['Type'] != 'Banner')
		{
			foreach ($question['Answers'] as $answer)
			{
				if ($answer['Value'] == 'checked')
				{
					$score += $answer['Score'];
				}
			}
		}
		
		return $score;
	}
	
	public function SectionNameToIndex($sectionName)
	{
		$sectionIndex = 0;
		foreach ($this->sections as $index=>$section)
		{
			if ( $section['SectionName'] == $sectionName )
			{
				$sectionIndex = $index;
				break;
			}
		}
		return $sectionIndex;
	}
	
	private function SetupAnswerIDs()
	{	
		// Loop through the model and assign a unique ID to each question and answer to assist with form rendering and submission
		foreach ($this->sections as $sectionIndex=>&$section)
		{
			if ( !isset($section['HasSubCategories']) )
			{
				$section['HasSubCategories'] = FALSE;
			}
			foreach ($section['Questions'] as $questionIndex=>&$question)
			{
				if ( $question['Type'] != 'Banner')
				{
					$question['ID'] = 'S' . ($sectionIndex + 1) . '-Q' . ($questionIndex + 1);
					
					if ( !isset($question['Answers']) )
					{
						// Add default yes/no answers
						$question['Answers'] = array( array('Answer' => 'Yes', 'Score' => 1), array('Answer' => 'No', 'Score' => 0) );
					}
					
					foreach ($question['Answers'] as $answerIndex=>&$answer)
					{
						$answer['ID'] = 'S' . ($sectionIndex + 1) . '-Q' . ($questionIndex + 1) . '-A' . ($answerIndex + 1);
						if (!isset($answer['Value']))
						{
							$answer['Value'] = '';
						}
					}
				}
				if ( isset($question['SubCategory']) )
				{
					$section['HasSubCategories'] = TRUE;
				}			
			}
		}
	}
	
	private function SaveResponses()
	{	
		// Loop through each question in our session storage and, if we find post variable matching the question ID, then save the answer
		foreach ($this->sections as $sectionIndex=>&$section)
		{
			foreach ($section['Questions'] as $questionIndex=>&$question)
			{
				if ( $question['Type'] == 'Option' )
				{
					if ( isset($_POST[$question['ID']]) )
					{
						foreach ($question['Answers'] as $answerIndex=>&$answer)
						{
							if ( $answer['ID'] == $_POST[$question['ID']] )
							{
								$answer['Value'] = 'checked';
							}
							else
							{
								$answer['Value'] = '';
							}
						}
					}
				}
				
				if ( $question['Type'] == 'Checkbox' )
				{
					foreach ($question['Answers'] as $answerIndex=>&$answer)
					{
						// If hidden field is there then we can use the presense of the non-hidden field to determine if the checkbox was checked
						if ( isset($_POST[$answer['ID'] . '-hidden']) )
						{
							if ( isset($_POST[$answer['ID']] ) )
							{
								$answer['Value'] = 'checked';
							}
							else
							{
								$answer['Value'] = '';
							}
						}
					}
				}
			}
		}
	}
	
}

?>