<?php
/**
 * $Id: 2beb14d928ee47f36cceb9467b4a2ac9d2c81ef4 $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://phing.info>.
 */

require_once 'phing/Task.php';
require_once 'phing/tasks/ext/git/GitBaseTask.php';

/**
 * Switches a repository at a given local directory to a different location
 *
 * @author Dom Udall <dom.udall@clock.co.uk>
 * @version $Id: 2beb14d928ee47f36cceb9467b4a2ac9d2c81ef4 $
 * @package phing.tasks.ext.svn
 * @since 2.4.3
 */
class AjxpGitSummarizeTask extends GitBaseTask
{
    /**
     * Which Revision to Export
     *
     * @todo check if version_control_svn supports constants
     *
     * @var string
     */
    private $commit1 = '';
    private $commit2 = '';
    private $summarizeFile;

    /**
     * The main entry point
     *
     * @throws BuildException
     */
    public function main()
    {
       if (null === $this->getRepository()) {
            throw new BuildException('"repository" is required parameter');
        }

        $client = $this->getGitClient(false, $this->getRepository());
        $command = $client->getCommand('diff-tree');
        $command->setOption('r');
        $command->setOption('name-status', true);
        $command->addArgument($this->getCommit1());
        $command->addArgument($this->getCommit2());

        $this->log("Diffing Git repository '" . $this->getRepository() . "' "
          . " (revision: {$this->getCommit1()}:{$this->getCommit2()}");

         try {
            $output = $command->execute();
         } catch (Exception $e) {
             throw new BuildException('Task execution failed.');
         }
        file_put_contents($this->getSummarizeFile(), $output);

    }

    public function setCommit1($revision)
    {
        $this->commit1 = $revision;
    }

    public function getCommit1()
    {
        return $this->commit1;
    }

    public function setCommit2($revision)
    {
        $this->commit2 = $revision;
    }

    public function getCommit2()
    {
        return $this->commit2;
    }

    public function setSummarizeFile($summarizeFile)
    {
        $this->summarizeFile = $summarizeFile;
    }

    public function getSummarizeFile()
    {
        return $this->summarizeFile;
    }

}
