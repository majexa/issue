<?php

class IssueCli extends CliHelpArgsSingle {

  //protected $servers;

  //protected function init() {
  //  $this->servers = require __DIR__.'/.remoteTestServers.php';
  //}

  //static function sshString(array $server) {
  //  return 'ssh user@'.$server['host'].(isset($server['port']) ? ' -p '.$server['port'] : '');
  //}

  protected function _run(CliArgs $args) {
    parent::_run($args);
    //if ($args->method == 'opened') {
      //  $sshString = self::sshString($server);
      //  output2('Remote server:');
      //  print `$sshString issueSlave $this->initArgv`;
    //}
    //foreach ($this->servers as $server) {
    //}
  }

}