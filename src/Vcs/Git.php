<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS-IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

namespace Muhafiz\Vcs;

use Muhafiz\Utils\System as Sys;
use Muhafiz\Vcs\VcsAbstract as VcsAbstract;

/**
 * Git helper which supplies a high-level API for git command
 */
class Git extends VcsAbstract
{
    /**
     * return list of staged files
     * @return array
     */
    public function getStagedFiles()
    {
        return Sys::runCommand("git diff --cached --name-only --diff-filter=ACM");
    }


    /**
     * return list of new added files
     * @return array
     */
    public function getNewFiles()
    {
        return $this->_removeFileFlag(Sys::runCommand("git status --short | grep ^A"));
    }


    /**
     * Get list of files between revisions by generating last file as a tmp
     * source so we can get them on pre-receive hook
     *
     * @param string $firstRev first revision
     * @param string $secondRev revision after commit
     * @return array list of files
     */
    public function getFilesAfterCommit($firstRev, $secondRev)
    {
        $tmpDir = "/tmp/"; //TODO: this should be configurable

        $files = array();

        $diff = Sys::runCommand("git diff --name-only $firstRev $secondRev 2> /dev/null");

        foreach ($diff['output'] as $file) {
            $tree = Sys::runCommand("git ls-tree $secondRev $file 2> /dev/null");

            //on file remove events
            if (!isset($tree['output'][0])) continue;

            $tree = preg_split('/\s/', $tree['output'][0]);

            $tmpFile = $tmpDir . uniqid("muhafiz_") . "/" . $file;

            $path = pathinfo($tmpFile);

            Sys::runCommand("mkdir -p ".$path['dirname']); // subdirectory files needs directory hierarchy

            Sys::runCommand("git cat-file $tree[1] $tree[2] > ${tmpFile} 2> /dev/null");

            $files[] = $tmpFile;
        }

        return $files;
    }


    /**
     * read config from given key
     * @param string $key key to read
     * @param string|null $defaultValue default value for key, if value not set
     * @return string
     */
    public function getConfig($key, $defaultValue = null)
    {
        $result = Sys::runCommand("git config ${key}");
        return isset($result['output'][0]) ? $result['output'][0] : $defaultValue;
    }


    /**
     * Set git config by key/value
     * @param string $key key to set
     * @param string $value value for key
     * @return boolean
     */
    public function setConfig($key, $value)
    {
        $result = Sys::runCommand("git config ${key} ${value}");
        return $result['exitCode'] == 0;
    }


    /**
     * Gets the cmd to print contents of changed file
     * @param string $file file to print
     * @return string cat cmd
     */
    public function catCommand($file)
    {
        return "cat ${file}";
    }


    /**
     * Removes any file flag like A, M, ?? from file
     * @param array $result
     * @return array
     */
    private function _removeFileFlag($result)
    {
        $result['output'] = array_map(
            function ($item) {
                return trim(preg_replace("/^.*? /", "", $item));
            }, $result['output']
        );

        return $result;
    }


    /**
     * Determines the vcs uses stdout or not
     * @return bool true if vcs uses stdout
     */
    public function usesStdout()
    {
        return false;
    }
}
