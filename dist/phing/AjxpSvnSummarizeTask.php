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
require_once 'phing/tasks/ext/svn/SvnBaseTask.php';

/**
 * Switches a repository at a given local directory to a different location
 *
 * @author Dom Udall <dom.udall@clock.co.uk>
 * @version $Id: 2beb14d928ee47f36cceb9467b4a2ac9d2c81ef4 $
 * @package phing.tasks.ext.svn
 * @since 2.4.3
 */
class AjxpSvnSummarizeTask extends SvnBaseTask
{
    /**
     * Which Revision to Export
     *
     * @todo check if version_control_svn supports constants
     *
     * @var string
     */
    private $revision1 = '';
    private $revision2 = '';
    private $summarizeFile;

    /**
     * The main entry point
     *
     * @throws BuildException
     */
    function main()
    {
        $this->setup('diff');

        $this->log("Diffing SVN repository '" . $this->getRepositoryUrl() . "' "
          . " (revision: {$this->getRevision1()}:{$this->getRevision2()})");

        // revision
        $switches = array(
            'r' => $this->getRevision1().":".$this->getRevision2(),
        );

        $output = $this->run(array('--summarize'), $switches);
        file_put_contents($this->getSummarizeFile(), $output);
    }

    public function setRevision1($revision)
    {
        $this->revision1 = $revision;
    }
    
    public function getRevision1()
    {
        return $this->revision1;
    }
    
    public function setRevision2($revision)
    {
        $this->revision2 = $revision;
    }
    
    public function getRevision2()
    {
        return $this->revision2;
    }
    public function setSummarizeFile($summarizeFile){
    	$this->summarizeFile = $summarizeFile;
    }
    public function getSummarizeFile(){
    	return $this->summarizeFile;
    }
    
}
