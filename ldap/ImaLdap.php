<?php

namespace demetrio77\imautils\ldap;

use yii\base\Object;
use demetrio77\imautils\ldap\LdapImaTrait;

class ImaLdap extends Object
{
	use LdapImaTrait;
	
	public function init()
	{
		parent::init();
		$this->initialize();
		$this->connect();
	}
}