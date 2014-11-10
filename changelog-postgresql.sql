DROP TABLE IF EXISTS databasechangelog CASCADE;
CREATE TABLE databasechangelog
(
   id             varchar(255)    NOT NULL,
   author         varchar(255)    NOT NULL,
   filename       varchar(255)    NOT NULL,
   dateexecuted   timestamp       NOT NULL,
   orderexecuted  integer         NOT NULL,
   exectype       varchar(10)     NOT NULL,
   md5sum         varchar(35),
   description    varchar(255),
   comments       varchar(255),
   tag            varchar(255),
   liquibase      varchar(20)
);

COMMIT;
