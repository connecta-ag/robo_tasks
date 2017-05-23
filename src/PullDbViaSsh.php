<?php namespace CAG\Robo\Task;

use Robo\Task\Remote\loadTasks as Remote;
use RuntimeException;
use Robo\Result;
use Robo\Task\Base\loadTasks as Base;
use CAG\Robo\Task\loadTasks as CAGTasks;
use Robo\Common\DynamicParams;
use Robo\Common\BuilderAwareTrait;

/**
 * Class PullDbViaSsh
 *
 * This class will help developers to automatically update their local database from the remote one configured in .env *
 *
 * @package CAG\Robo\Task
 */

class PullDbViaSsh extends \Robo\Task\BaseTask implements \Robo\Contract\BuilderAwareInterface
{
    use Base, DynamicParams, CAGTasks, BuilderAwareTrait, Remote;

    /** @var string */
    private $sshHost;

    /** @var string */
    private $sshUser;

    /** @var string */
    private $sshPass;

    /** @var string */
    private $sshKey;

    /** @var string */
    private $remoteDbHost = 'localhost';

    /** @var string */
    private $remoteDbUser = 'root';

    /** @var string */
    private $remoteDbPass;

    /** @var string */
    private $remoteDbName;

    /** @var string */
    private $localDbHost = 'localhost';

    /** @var string */
    private $localDbUser = 'root';

    /** @var string */
    private $localDbPass = 'root';

    /** @var string */
    private $localDbName;

    /**
     * Executes the PullDbViaSsh Task.
     *
     * @return Result
     */
    public function run()
    {

        // Get task collection (via Traits?) // TODO: do we need that? Are there alternatives?
        $collection = $this->collectionBuilder();

        // Set sql dump filename
        $sqlDumpFilename = $this->remoteDbName . '_' . time() . '.sql';

        // Create our dump on the remote server (requires ~/.my.cnf)
        // TODO: instead of ignoring tables rather only ignore the data dump, but include structure, please
        $cmd = 'mysqldump ' .
            ' --single-transaction ' .
            //' --structure-tables=' . escapeshellarg(implode(',', $this->getStructureOnlyTableList())) . // TODO: make this happen!
            ' ' . $this->remoteDbName . ' > /tmp/' . $sqlDumpFilename;

        // 1) create a gzipped dump on the server // TODO: add the gzip part // TODO: Maybe add exception handling here, if file does not exist
        $this->printTaskInfo('Dumping DB on remote server - <info>'.$cmd.'</info>');
        $collection->taskSshExec($this->sshHost, $this->sshUser)
            ->remoteDir('/tmp')
            ->exec($cmd)
            ->run();

        // 2) download the dump // TODO: Add exception handling here, if file does not exist
        $this->printTaskInfo('Downloading SQL dump <info>'.$sqlDumpFilename.'</info>');
        $collection->taskRsync()
            ->fromHost($this->sshHost)
            ->fromPath('/tmp/' . $sqlDumpFilename)
            ->fromUser($this->sshUser)
            ->toPath('./_temp/')
            ->checksum()
            ->wholeFile()
            ->verbose()
            ->progress()
            ->humanReadable()
            ->stats()
            ->run();

        // 3) delete the dump on the server
        $this->printTaskInfo('Deleting SQL dump on remote server <info>'.$sqlDumpFilename.'</info>');
        $collection->taskSshExec($this->sshHost, $this->sshUser)
            ->remoteDir('/tmp')
            ->exec("rm -f ./$sqlDumpFilename")
            ->run();

        // 4) import the dump locally
        $this->printTaskInfo("Importing SQL dump into <info>$this->localDbName@$this->localDbHost</info>: <info>./_temp/$sqlDumpFilename</info>");
        if (
        !$collection->taskImportSqlDump("./_temp/$sqlDumpFilename")
            ->host($this->localDbHost)
            ->user($this->localDbUser)
            ->pass($this->localDbPass)
            ->name($this->localDbName)
            ->run()->wasSuccessful()
        ) {
            throw new RuntimeException('Failed to import dump on local server.');
        }

        // 5) delete the dump locally
        $this->printTaskInfo('Deleting dump locally.');
        unlink("./_temp/$sqlDumpFilename");

        // If we get to here assume everything worked
        return Result::success($this);
    }

    /**
     * Return structure-only database table names.
     *
     * This is used to make the exported file as small as possible. All returned
     * database table names indicate to only export their structure but not data
     * rows.
     *
     * @return array
     *   An array of database table names.
     */
    protected function getStructureOnlyTableList() {
        $tables = [
            'cf_*',
            'fe_session_*',
            'sys_history',
            'sys_log',
        ];

        return $tables;
    }
}
