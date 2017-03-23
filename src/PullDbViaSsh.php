<?php namespace CAG\Robo\Task;

use Robo\Collection\CollectionBuilder;
use RuntimeException;
use Robo\Result;
use Robo\Task\Base\loadTasks as Base;
use CAG\Robo\Task\loadTasks as CAGTasks;
use Robo\Common\DynamicParams;
use CAG\Robo\Task\ImportSqlDump;
use phpseclib\Net\SFTP;
use phpseclib\Crypt\RSA;
use Robo\Common\BuilderAwareTrait;

class PullDbViaSsh extends \Robo\Task\BaseTask implements \Robo\Contract\BuilderAwareInterface
{
	use Base, DynamicParams, CAGTasks, BuilderAwareTrait;

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
	private $localDbPass;

	/** @var string */
	private $localDbName;

	/**
	 * Executes the PullDbViaSsh Task.
	 *
	 * @return Result
	 */
	public function run()
	{
		// Login to the remote server
		$this->printTaskInfo('Logging into remote server - <info>ssh://'.$this->sshUser.'@'.$this->sshHost.'/</info>');
		$ssh = new SFTP($this->sshHost);

		// Do we use password or a key
		if (file_exists($this->sshKey) && empty($this->sshPass))
		{
			$key = new RSA();
			$key->loadKey(file_get_contents($this->sshKey));
			if (!$ssh->login($this->sshUser, $key))
			{
				throw new RuntimeException
				(
					'Failed to login via SSH using Key Based Auth.'
				);
			}
		}
		else
		{
			if (!$ssh->login($this->sshUser, $this->sshPass))
			{
				throw new RuntimeException
				(
					'Failed to login via SSH using Password Based Auth.'
				);
			}
		}

		// Create our dump filename
		$dump_name = $this->remoteDbName.'_'.time();

		// Create our dump on the remote server
		$cmd = 'mysqldump '.
			'-h'.$this->remoteDbHost.
			' -u'.$this->remoteDbUser.
			' '.(empty($this->remoteDbPass) ? '' : '-p'.$this->remoteDbPass).
            ' --single-transaction \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_cagevents_category \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_cagevents_category_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_hash \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_hash_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_imagesizes \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_imagesizes_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_news_category \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_news_category_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_pages \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_pages_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_pagesection \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_pagesection_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_rootline \
             --ignore-table=' . $this->remoteDbUser . '.cf_cache_rootline_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_datamapfactory_datamap \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_datamapfactory_datamap_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_object \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_object_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_reflection \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_reflection_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_typo3dbbackend_queries \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_typo3dbbackend_queries_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_typo3dbbackend_tablecolumns \
             --ignore-table=' . $this->remoteDbUser . '.cf_extbase_typo3dbbackend_tablecolumns_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_tx_solr \
             --ignore-table=' . $this->remoteDbUser . '.cf_tx_solr_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_vhs_main \
             --ignore-table=' . $this->remoteDbUser . '.cf_vhs_main_tags \
             --ignore-table=' . $this->remoteDbUser . '.cf_vhs_markdown \
             --ignore-table=' . $this->remoteDbUser . '.cf_vhs_markdown_tags \
             --ignore-table=' . $this->remoteDbUser . '.fe_session_data \
             --ignore-table=' . $this->remoteDbUser . '.fe_sessions \
             --ignore-table=' . $this->remoteDbUser . '.sys_history \
             --ignore-table=' . $this->remoteDbUser . '.sys_log '.
			' '.$this->remoteDbName.' > /tmp/'.$dump_name.'.sql'
		;
		$this->printTaskInfo('Dumping db on remote server - <info>'.$cmd.'</info>');
		$results = $ssh->exec($cmd);
		if ($ssh->getExitStatus() > 0)
		{
			throw new RuntimeException
			(
				'Failed to create dump on remote server. '.
				$results
			);
		}

		// Compressing dump
		$cmd = 'gzip /tmp/'.$dump_name.'.sql';
		$this->printTaskInfo('Compressing dump on remote server - <info>'.$cmd.'</info>');
		$results = $ssh->exec($cmd);
		if ($ssh->getExitStatus() > 0)
		{
			throw new RuntimeException
			(
				'Failed to compress dump on remote server. '.
				$results
			);
		}

		// Copy it down locally
		$this->printTaskInfo('Transfering dump to local.');
		$temp_dump_name = tempnam(sys_get_temp_dir(), 'dump');
		$temp_dump = $temp_dump_name.'.sql.gz';
        $this->printTaskInfo('Move dump from remote to local. Local path: <info>'.$temp_dump.'</info>');
		if (!$ssh->get('/tmp/'.$dump_name.'.sql.gz', $temp_dump))
		{
			throw new RuntimeException('Failed to download dump.');
		}

		// Remove the dump from the remote server
		$this->printTaskInfo('Removing dump from remote server - <info>rm /tmp/'.$dump_name.'.sql.gz</info>');
		if (!$ssh->delete('/tmp/'.$dump_name.'.sql.gz'))
		{
			throw new RuntimeException('Failed to delete dump on remote server.');
		}

		// Import the dump locally

        $collection = $this->collectionBuilder();
		if (
			!$collection->taskImportSqlDump($temp_dump)
                ->host($this->localDbHost)
                ->user($this->localDbUser)
                ->pass($this->localDbPass)
                ->name($this->localDbName)
                ->run()->wasSuccessful()
		){
			throw new RuntimeException('Failed to import dump on local server.');
		}

		$this->printTaskInfo('Deleting dump locally.');
		unlink($temp_dump); unlink($temp_dump_name);

		// If we get to here assume everything worked
		return Result::success($this);
	}
}
