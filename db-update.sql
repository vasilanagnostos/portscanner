ALTER TABLE server_list ADD `type` VARCHAR(20) DEFAULT NULL;

TRUNCATE server_list;

INSERT INTO `server_list` (`idserver`, `address`, `port`, `expected_result`, `type`)
VALUES
	(1, 'www.google.com', 80, '^200|302 ', 'web'),
	(4, 'smtp.gmail.com', 25, '^220 ', 'mail'),
	(5, 'speedtest.tele2.net', 21, '^220 ', 'ftp');
