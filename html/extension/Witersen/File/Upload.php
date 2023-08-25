<?php

/*
 *@Author: www.witersen.com
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace Witersen;

class Upload
{

    /**
     *Temporary storage directory for file fragmentation
     *
     *@var string
     */
    private $nameDirTempSave = '';

    /**
     *The official storage directory of the final document
     *
     *@var string
     */
    private $nameDirSave = '';

    /**
     *file name
     *
     *@var string
     */
    private $nameFileSave = '';

    /**
     *md5 of the complete file
     *
     *@var string
     */
    private $nameFileMd5 = '';

    /**
     *php temporary file path
     *
     *@var string
     */
    private $nameFileCurrent = '';

    /**
     *The first few file fragments
     *
     *@var integer
     */
    private $numBlobCurrent = 0;

    /**
     *Total number of file fragments
     *
     *@var integer
     */
    private $numBlobTotal = 0;

    /**
     *The number of file fragments that have been uploaded
     *
     *@var integer
     */
    private $completeCount = 0;

    /**
     *Whether the merge is complete
     *
     *@var boolean
     */
    private $complete = false;

    /**
     *Working status
     *
     *@var boolean
     */
    private $status = true;

    /**
     *Prompt information
     *
     *@var string
     */
    private $message = '上传完成';

    /**
     *Whether to delete files immediately after merging
     *
     *@var boolean
     */
    private $deleteOnMerge = true;

    /**
     *Upload
     *
     *@param string $nameDirTempSave Temporary storage directory for file fragments
     *@param string $nameDirSave Official archive for final documents
     *@param string $nameFileSave The official file name of the final document
     *@param string $nameFileMd5 The md5 value of the file to upload
     *@param string $nameFileCurrent Path of current file segment
*@param integer $numBlobCurrent What is the current file segment
     *@param integer $numBlobTotal There are several file fragments
     *@param integer $deleteOnMerge Whether to delete all fragments after file merge is complete
     *@return void
     */
    public function __construct($nameDirTempSave, $nameDirSave, $nameFileSave, $nameFileMd5, $nameFileCurrent, $numBlobCurrent, $numBlobTotal, $deleteOnMerge = true)
    {
        $this->nameDirTempSave = $nameDirTempSave;
        $this->nameDirSave = $nameDirSave;
        $this->nameFileSave = $nameFileSave;
        $this->nameFileMd5 = $nameFileMd5;
        $this->nameFileCurrent = $nameFileCurrent;
        $this->numBlobCurrent = $numBlobCurrent;
        $this->numBlobTotal = $numBlobTotal;
        $this->deleteOnMerge = $deleteOnMerge;
    }

    /**
     *Save files in pieces
     *
     *@return void
     */
    public function fileUpload()
    {
        if (!file_exists($this->nameDirTempSave . '/' . $this->nameFileMd5 . '_' . $this->numBlobTotal . '_' . $this->numBlobCurrent)) {
            move_uploaded_file($this->nameFileCurrent, $this->nameDirTempSave . '/' . $this->nameFileMd5 . '_' . $this->numBlobTotal . '_' . $this->numBlobCurrent);
        }

        $count = 0;
        clearstatcache();
        $files = scandir($this->nameDirTempSave);
        foreach ($files as $file) {
            if ($file == '.' && $file == '..') {
                continue;
            }
            if (is_dir($this->nameDirTempSave . '/' . $file)) {
                continue;
            }
            if (!preg_match(sprintf('/^%s_%s_[0-9]+$/', $this->nameFileMd5, $this->numBlobTotal), $file, $match)) {
                continue;
            }
            $count++;
        }

        $this->completeCount = $count;

        if ($count == $this->numBlobTotal) {
            $this->fileMerge();
        }
    }

    /**
     *File sharding and merging
     *
     *@return void
     */
    private function fileMerge()
    {
        $fwrite = fopen($this->nameDirSave . '/' . $this->nameFileSave, 'ab');

        for ($i = 1; $i <= $this->numBlobTotal; $i++) {
            $slicename = $this->nameDirTempSave . '/' . $this->nameFileMd5 . '_' . $this->numBlobTotal . '_' . $i;
            clearstatcache();
            if (!file_exists($slicename)) {
                $this->status = false;
                $this->message = sprintf('第[%s]个分片文件[%s]不存在', $i, $slicename);
                return;
            }
        }

        for ($i = 1; $i <= $this->numBlobTotal; $i++) {
            $slicename = $this->nameDirTempSave . '/' . $this->nameFileMd5 . '_' . $this->numBlobTotal . '_' . $i;
            clearstatcache();
            if (!file_exists($slicename)) {
                $this->status = false;
                $this->message = sprintf('第[%s]个分片文件[%s]不存在', $i, $slicename);
                return;
            }

            //file fragment merge
            $fsize = filesize($slicename);
            if ($fsize > 0) {
                $fread = fopen($slicename, 'rb');
                fwrite($fwrite, fread($fread, $fsize));
                fclose($fread);
                unset($fread);
            }

            //File fragment deletion
            if ($this->deleteOnMerge) {
                @unlink($slicename);
            }
        }

        fclose($fwrite);

        $this->complete = true;
    }

    /**
     *returned messages
     *
     *@return array
     */
    public function message()
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => [
                'completeCount' => $this->completeCount,
                'complete' => $this->complete
            ]
        ];
    }
}
