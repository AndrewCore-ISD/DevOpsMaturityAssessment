
CREATE TABLE `questiontype` (
  `QuestionTypeId` int(11) NOT NULL,
  `Type` int(11) NOT NULL,
  PRIMARY KEY (`QuestionTypeId`)
);

CREATE TABLE `section` (
  `SectionId` int(11) NOT NULL,
  `Title` varchar(100) NOT NULL,
  PRIMARY KEY (`SectionId`)
);

CREATE TABLE `question` (
  `QuestionId` int(11) NOT NULL,
  `SectionId` int(11) NOT NULL,
  `QuestionTypeId` int(11) NOT NULL,
  `Text` mediumtext NOT NULL,
  PRIMARY KEY (`QuestionId`),
  KEY `FK_Question_Section` (`SectionId`),
  KEY `FK_Question_QuestionType` (`QuestionTypeId`),
  CONSTRAINT `FK_Question_QuestionType` FOREIGN KEY (`QuestionTypeId`) REFERENCES `questiontype` (`QuestionTypeId`),
  CONSTRAINT `FK_Question_Section` FOREIGN KEY (`SectionId`) REFERENCES `section` (`SectionId`)
);

CREATE TABLE `questionanswer` (
  `QuestionAnswerId` int(11) NOT NULL AUTO_INCREMENT,
  `QuestionId` int(11) NOT NULL,
  `Text` mediumtext NOT NULL,
  `Points` decimal(2,1) NOT NULL,
  PRIMARY KEY (`QuestionAnswerId`),
  KEY `FK_QuestionAnswer_Question` (`QuestionId`),
  CONSTRAINT `FK_QuestionAnswer_Question` FOREIGN KEY (`QuestionId`) REFERENCES `question` (`QuestionId`)
);

CREATE TABLE `survey` (
  `SurveyId` int(11) NOT NULL AUTO_INCREMENT,
  `SubmitDate` datetime NOT NULL,
  `guid` char(50) DEFAULT NULL,
  PRIMARY KEY (`SurveyId`),
  UNIQUE KEY `guid` (`guid`)
);

CREATE TABLE `surveyanswer` (
  `SurveyAnswerId` int(11) NOT NULL AUTO_INCREMENT,
  `SurveyId` int(11) NOT NULL,
  `QuestionId` int(11) NOT NULL,
  `AnswerId` int(11) NOT NULL,
  PRIMARY KEY (`SurveyAnswerId`),
  KEY `FK_SurveyAnswer_Survey` (`SurveyId`),
  KEY `FK_SurveyAnswer_Question` (`QuestionId`),
  KEY `FK_SurveyAnswer_QuestionAnswer` (`AnswerId`),
  CONSTRAINT `FK_SurveyAnswer_Question` FOREIGN KEY (`QuestionId`) REFERENCES `question` (`QuestionId`),
  CONSTRAINT `FK_SurveyAnswer_QuestionAnswer` FOREIGN KEY (`AnswerId`) REFERENCES `questionanswer` (`QuestionAnswerId`),
  CONSTRAINT `FK_SurveyAnswer_Survey` FOREIGN KEY (`SurveyId`) REFERENCES `survey` (`SurveyId`)
);

CREATE TABLE `surveyraw` (
  `SurveyRawId` int(11) NOT NULL AUTO_INCREMENT,
  `SurveyId` int(11) NOT NULL,
  `Raw` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`Raw`)),
  PRIMARY KEY (`SurveyRawId`),
  KEY `FK_SurveyRaw_Survey` (`SurveyId`),
  CONSTRAINT `FK_SurveyRaw_Survey` FOREIGN KEY (`SurveyId`) REFERENCES `survey` (`SurveyId`)
);