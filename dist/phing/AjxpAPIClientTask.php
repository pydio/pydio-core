<?php
/**
 * Created by JetBrains PhpStorm.
 * User: admin
 * Date: 15/07/13
 * Time: 14:15
 * To change this template use File | Settings | File Templates.
 */

require_once 'phing/Task.php';

class AjxpAPIClientTask extends Task
{

    private $cliPath;
    private $user;
    private $password;

    private $repository = 'ajxp_conf';
    private $params;
    private $action;

    function main(){

        if(isSet($this->user) && isSet($this->password) && isSet($this->repository)){
            $cmd = "php {$this->cliPath}/cmd.php -a={$this->action} -u={$this->user} -p={$this->password} -r={$this->repository} {$this->params}";
        }else{
            // Anon mode
            $cmd = "php {$this->cliPath}/cmd.php -a={$this->action} {$this->params}";
        }
        passthru($cmd);

    }

    function createProperty(){
        var_dump(func_get_args());
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setCliPath($cliPath)
    {
        $this->cliPath = $cliPath;
    }

    public function getCliPath()
    {
        return $this->cliPath;
    }

    public function setParams($params)
    {
        $this->params = $params;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setRepository($repository)
    {
        $this->repository = $repository;
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

}
