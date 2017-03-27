<?php namespace CAG\Robo\Task;

use Robo\Collection\CollectionBuilder;
use RuntimeException;
use Robo\Result;
use Robo\Task\Base\loadTasks as Base;
use Robo\Common\BuilderAwareTrait;

/**
 * Class SyncFiles
 * @package CAG\Robo\Task
 *
 * Robo Task to sync files between remote and local environment
 *
 *  $this->taskContentSync()
 *      ->host(getenv('CONTENT_SYNC_HOST'))
 *      ->folders(getenv('CONTENT_SYNC_FOLDERS'))
 *      ->remoteUser('m.krams')
 *      ->remoteBasePath(getenv('CONTENT_SYNC_HOST_BASE_PATH'))
 *      ->localBasePath(self::BASE_DIR)
 *      ->localPathCorrection(getenv('CONTENT_SYNC_LOCAL_BASE_PATH_CORRECTION'))
 *      ->run();
 *
 */
class SyncFiles extends \Robo\Task\BaseTask implements \Robo\Contract\BuilderAwareInterface
{
	use DynamicParams, BuilderAwareTrait;

	/** @var string */
	private $folders;

	/** @var string */
	private $host;

	/** @var string */
	private $remoteUser;

	/** @var string */
	private $remoteBasePath;

	/** @var string */
	private $localBasePath;

	/** @var string */
	private $localPathCorrection;

	/**
	 * Executes the PullDbViaSsh Task.
	 *
	 * @return Result
	 */
	public function run()
	{
        $folders = explode(',', $this->folders);

        if(empty($folders)) {
            return Result::error($this);
        }

        foreach ($folders as $folder) {
            $this->say('Sync folder: ' . $folder);

            // add path correction between
            if($this->localPathCorrection) {
                $folderLocal = $this->localPathCorrection . $folder;
            }else {
                $folderLocal = $folder;
            }

            $this->remoteBasePath = $this->addTrailingSlash($this->remoteBasePath);
            $this->localBasePath = $this->addTrailingSlash($this->localBasePath);

            $this->taskRsync()
                ->fromHost($this->host)
                ->fromPath($this->remoteBasePath . '/' . $folder)
                ->fromUser($this->remoteUser)
                ->toPath($this->localBasePath . '/' . $folderLocal)
                ->recursive()
                ->checksum()
                ->wholeFile()
                ->verbose()
                ->progress()
                ->humanReadable()
                ->stats()
                ->run();
        }

		// If we get to here assume everything worked
		return Result::success($this);
	}

    private function addTrailingSlash($string)
    {
        if(substr($string, -1) != '/') {
            $string .= '/';
        }
        return $string;
	}
}
