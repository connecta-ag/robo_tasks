<?php
/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2017 Connecta AG Dev Team <typo3@connecta.ag>, Connecta AG
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace CAG\Robo\Task;


trait loadTasks
{
    /**
     * Task to compile assets
     *
     * @return \CAG\Robo\Task\CreateDb
     */
    protected function taskCreateDb()
    {
        return $this->task(CreateDb::class);
    }

    /**
     * Task to compile assets
     *
     * @return \CAG\Robo\Task\ImportSqlDump
     */
    protected function taskImportSqlDump($dump)
    {
        return $this->task(ImportSqlDump::class, $dump);
    }

    /**
     * Task to compile assets
     *
     * @return \CAG\Robo\Task\PullDbViaSsh
     */
    protected function taskPullDbViaSsh()
    {
        return $this->task(PullDbViaSsh::class);
    }
}