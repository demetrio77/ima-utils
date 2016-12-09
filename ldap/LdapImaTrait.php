<?php

namespace demetrio77\imautils\ldap;

trait LdapImaTrait {
	
	public $hosts;
	public $protocol=3;
	public $timeout=3000;	
	public $baseDn;
	public $usersDn;
	public $groupsDn;
	public $group;
	
	private   $link;
	private   $connectedHost;
	private   $acceptedUsersDn;
	
	protected function init( $params = [])
	{
		if (isset($params['hosts']))    $this->hosts = $params['hosts'];
		if (isset($params['protocol'])) $this->protocol = $params['protocol'];
		if (isset($params['timeout']))  $this->timeout = $params['timeout'];
		if (isset($params['baseDn']))   $this->baseDn = $params['baseDn'];
		if (isset($params['usersDn']))  $this->usersDn = $params['usersDn'];
		if (isset($params['groupsDn'])) $this->groupsDn = $params['groupsDn'];
		if (isset($params['group']))    $this->group = $params['group'];
		
		$this->initialize();
		return $this;
	}
	
	protected function initialize()
	{
		$expl = explode(';', $this->hosts);
		$this->hosts = [];
		foreach ($expl as $hostUrl) {
			$result = preg_match_all('/(ldap|ldaps)\:\/\/([\w\.\-]*)(\:(\d+))?/', $hostUrl, $matches);
			if($result) {
				$host = [];
				if (isset($matches[1][0]) && $matches[1][0]) {
					$host['protocol'] = $matches[1][0];
					$host['port'] = $host['protocol']=='ldaps' ? 636 :  ($host['protocol']=='ldap' ? 389 : 0);
				}
				if (isset($matches[2][0]) && $matches[2][0]) {
					$host['hostname'] = $matches[2][0];
				}
				if (isset($matches[4][0]) && $matches[4][0]) {
					$host['port'] = $matches[4][0];
				}
				$this->hosts[] = $host;
			}
		}
		
		$this->usersDn = explode(';', $this->usersDn);
	}
	
	protected function connect()
	{
		foreach ($this->hosts as $host) {
			$socket = fsockopen($host['hostname'], $host['port'], $errno, $errstr, 100);
			if ($socket) {
				$this->link = ldap_connect( $host['protocol'].'://'.$host['hostname'].'/');
				$this->connectedHost = $host['hostname'];
				break;
			}
		}
		if ($this->link) {
			ldap_set_option($this->link, LDAP_OPT_PROTOCOL_VERSION, $this->protocol);
			ldap_set_option($this->link, LDAP_OPT_NETWORK_TIMEOUT, $this->timeout);
			return $this;
		}
		else {
			throw new \Exception('Невозможно cоединиться с сервером LDAP');
		}
	}
	
	public function check($uid, $password)
	{
		if (!$uid) return false;
		try {
			foreach ($this->usersDn as $usersDn) {
				$userExists =  @ldap_bind($this->link, "uid=$uid,$usersDn,$this->baseDn", $password);
				if ($userExists) {
					$this->acceptedUsersDn = $usersDn;
					return true;
				}
			}
			return $userExists;
		}
		catch (\Exception $e) {echo $usersDn;echo $e;exit();
			return false;
		}
		return false;		
	}
	
	public function uidInGroup($uid, $group = false)
	{
		if ($group===false) $group = $this->group;
		$res = ldap_list($this->link, "$this->groupsDn,$this->baseDn", "cn=$group",['memberUid']);
		$list = ldap_get_entries($this->link, $res);
		if ($list['count']<1) return false;
		return in_array($uid, $list[0]['memberuid']);
	}
	
	public function getRecord($uid, $fields = ['cn'])
	{
		$res = ldap_list($this->link, "$this->acceptedUsersDn,$this->baseDn", "uid=$uid", $fields);
		$list = ldap_get_entries($this->link, $res);
		if (isset($list[0])) return $list[0]; else return [];
	}
	
	public function getConnectedHost()
	{
		return $this->connectedHost;
	}
	
	public function getAcceptedUsersDn()
	{
		return $this->acceptedUsersDn;
	}
}