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

    private $displayResult = false;
    private $compareResult;

    public function main()
    {
        if (isSet($this->user) && isSet($this->password) && isSet($this->repository)) {
            $cmd = "php {$this->cliPath}/cmd.php -a={$this->action} -u={$this->user} -p={$this->password} -r={$this->repository} {$this->params}";
        } else {
            // Anon mode
            $cmd = "php {$this->cliPath}/cmd.php -a={$this->action} {$this->params}";
        }
        $result = array();
        exec($cmd, $result);
        $res = implode("", $result);
        if (isSet($this->compareResult)) {
            if (trim($res) == $this->compareResult) {
                $this->log("File content is correct");
            } else {
                // Do not break build
                //throw new BuildException("Content are not the same: '".$res."' versus '".$this->compareResult."'");
                $this->log("Content are not the same: '".$res."' versus '".$this->compareResult."'", Project::MSG_ERR);
            }

        } else {
            $this->log($this->repository.":".$this->action);
            if ($this->displayResult) {
                $this->log($res, Project::MSG_INFO);
            }
        }

    }

    /**
     * @return \Parameter
     */
    public function createParameter()
    {
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

    public function setCompareResult($compare_result)
    {
        $this->compareResult = $compare_result;
    }

    public function getCompareResult()
    {
        return $this->compareResult;
    }

    public function setDisplayResult($displayResult)
    {
        $this->displayResult = $displayResult;
    }

    public function getDisplayResult()
    {
        return $this->displayResult;
    }

}
