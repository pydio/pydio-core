<?php
/* An easy way to keep in track of external processes.
 * Ever wanted to execute a process in php, but you still wanted to have somewhat controll of the process ? Well.. This is a way of doing it.
 * @compability: Linux only. (Windows does not work).
 * @author: Peec
 */
class UnixProcess{
    private $pid;
    private $command;
    private $output;

    public function __construct($cl=false, $output=false){
        if($output != false) {
          	$this->output = $output;
        }else {
           	$this->output = "/dev/null";
        }
    	if ($cl != false){
            $this->command = $cl;
            $this->runCom();
        }
    }
    private function runCom(){
        $command = $this->command.' > '.$this->output.' 2>&1 & echo $!';
        exec($command ,$op);
        $this->pid = (int)$op[0];
        $this->command = $command;
    }

    public function setPid($pid){
        $this->pid = $pid;
    }

    public function getPid(){
        return $this->pid;
    }

    public function status(){
        $command = 'ps -p '.$this->pid;
        exec($command,$op);
        if (!isset($op[1]))return false;
        else return true;
    }

    public function start(){
        if ($this->command != '')$this->runCom();
        else return true;
    }

    public function stop(){
        $command = 'kill '.$this->pid;
        exec($command);
        if ($this->status() == false)return true;
        else return false;
    }
}
?>