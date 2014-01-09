<?php
$installer = $this;
$installer->startSetup();

$installer->run("
DROP TABLE IF EXISTS {$this->getTable('varnishgento_flags')};

CREATE TABLE {$this->getTable('varnishgento_flags')} (
  `flag_id` INT  NOT NULL AUTO_INCREMENT,
  `purge_url` varchar(255),
  `set_on` TIMESTAMP NOT NULL,
  `iniciator_login` varchar(255),
  `iniciator_name` varchar(255),
  `flushed` INT(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`flag_id`),
  INDEX (`flushed`),
  INDEX (`purge_url`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;");

$installer->endSetup();
?>