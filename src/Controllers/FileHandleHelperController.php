<?php

namespace RoshaniSTPL\utility\Controllers;

use Aws;
use Roshanistpl\Utility\ExceptionHelper;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Types\Boolean;
use Roshanistpl\Utility\Controllers\S3WrapperController;

class FileHandleHelperController {

    /**
     * Delete File from Local file system
     * @param string $file Basic file name with folder name as prefix like (abc/bcd.ext)
     * @return boolean
     */
    static public function FileDeleteLocal($file) {
        $path = app()->basePath('public/');

        $filePath = $path . trim($file);
        if (file_exists($filePath)) {
            $isRemoved = unlink($filePath);
        }
        return TRUE;
    }

    /**
     * Delete File from file system and if Environment is Production then delete file from AWS S3 Bucket 
     * @param string $file Basic file name with folder name as prefix like (abc/bcd.ext)
     * @param string $appEnv Environment of Application
     * @param string $awsAccessKeyId AWS Access Key Id
     * @param string $awsBucket AWS Bucket Name
     * @param string $bucketFilePrefix AWS Bucket File as prefix
     * @param boolean $isInventory True if image is of inventory
     * @return boolean
     */
    static public function FileDeleteObject($file, $appEnv, $awsAccessKeyId, $awsBucket, $bucketFilePrefix, $isInventory = FALSE) {
        try {
            $file = trim($file);
            if ($isInventory) {
                if (isset($file) && !empty($file)) {
                    goto deleteInventoryImg;
                }
            } else {
                if (isset($file) && !empty($file)) {
                    if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                        deleteInventoryImg:
                        self::FileDeleteLocal($file);
                    } else {
                        if ($awsAccessKeyId != '') {
                            if (self::FileExists($file, $appEnv, $awsBucket)) {
                                $s3 = AWS::createClient('s3');
                                $s3->deleteObject(array('Bucket' => $awsBucket, 'Key' => str_replace($bucketFilePrefix, "", $file)));
                            }
                        }
                    }
                }
            }
            return TRUE;
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for FileDeleteObject');
        }
    }

    /**
     * Copy file from source folder to destination folder and delete file from source folder.
     * @param string $file Basic file name with folder name as prefix like (abc/bcd.ext)
     * @param string $tempFolder Source folder name where currently file is available
     * @param string $fileFolderPrefix Destination folder name where file should be moved
     * @param string $appEnv Environment of Application
     * @param string $awsAccessKeyId AWS Access Key Id
     * @param string $awsBucket AWS Bucket Name
     * @param string $bucketFilePrefix AWS Bucket File as prefix
     * @param boolean $deleteOldMedis True if delete file from source folder else false
     * @param boolean $isInventory True if image is of inventory
     * @return string
     */
    static public function FileCopyObject($file, $tempFolder, $fileFolderPrefix, $appEnv, $awsAccessKeyId, $awsBucket, $bucketFilePrefix, $deleteOldMedis = FALSE, $isInventory = FALSE) {
        try {
            $fileUrl = '';
            $path = app()->basePath('public/');
            if ($isInventory) {
                goto storeInventoryImg;
            } else {
                if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                    storeInventoryImg:
                    $old_path = $path . $file;
                    $new_path = $path . str_replace($tempFolder . "/", $fileFolderPrefix . "/", $file);

                    if ($old_path != $new_path) {
                        copy($old_path, $new_path);
                    }
                    if (file_exists($new_path)) {
                        // chmod($new_path, 0777);
                        exec('sudo chmod -R 777 ' . $new_path);
                    }
                    $fileUrl = str_replace($tempFolder . "/", $fileFolderPrefix . "/", $file);
                    if ($deleteOldMedis) {
                        if ($old_path != $new_path) {
                            self::FileDeleteObject($file, $appEnv, $awsAccessKeyId, $awsBucket, $bucketFilePrefix, $isInventory);
                        }
                    }
                } else {
                    if ($awsAccessKeyId != '') {
                        $old_path = $file;
                        $new_path = str_replace($tempFolder . "/", $fileFolderPrefix . "/", $file);
                        $s3 = AWS::createClient('s3');
                        $s3->copyObject(array(
                            'Bucket' => $awsBucket,
                            'Key' => $new_path,
                            'CopySource' => $awsBucket . "/" . $old_path,
                        ));
                        $fileUrl = $new_path;
                    }
                    if ($deleteOldMedis) {
                        if ($old_path != $new_path) {
                            self::FileDeleteObject($file, $appEnv, $awsAccessKeyId, $awsBucket, $bucketFilePrefix, $isInventory);
                        }
                    }
                }
            }
            return $fileUrl;
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for FileCopyObject');
        }
    }

    /**
     * Create duplicate file from source file path to destination file path.
     * @param string $source Source File path like (abc/bcd.ext)
     * @param string $destination new file path to copy source file path like (xyz/xyz.ext)
     * @param string $appEnv Environment of Application
     * @param string $awsAccessKeyId AWS Access Key Id
     * @param string $awsBucket AWS Bucket Name
     * @return string
     */
    static public function FileduplicateObject($source, $destination, $appEnv, $awsAccessKeyId, $awsBucket) {
        try {
            $fileUrl = '';
            $path = app()->basePath('public/');
            if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                $old_path = $path . $source;
                $new_path = $path . $destination;

                if ($old_path != $new_path) {
                    copy($old_path, $new_path);
                }
                if (file_exists($new_path)) {
                    // chmod($new_path, 0777);
                    exec('sudo chmod -R 777 ' . $new_path);
                }
                $fileUrl = $destination;
            } else {
                if ($awsAccessKeyId != '') {
                    $old_path = $source;
                    $new_path = $destination;
                    $s3 = AWS::createClient('s3');
                    $s3->copyObject(array(
                        'Bucket' => $awsBucket,
                        'Key' => $new_path,
                        'CopySource' => $awsBucket . "/" . $old_path,
                    ));
                    $fileUrl = $new_path;
                }
            }
            return $fileUrl;
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for FileduplicateObject');
        }
    }

    /**
     * Upload to S3 at temp folder
     * @param string $file Basic file name with folder name as prefix like (abc/bcd.ext)
     * @param string $tempFolder Source folder name where currently file is available
     * @param string $appEnv Environment of Application
     * @param string $awsAccessKeyId AWS Access Key Id
     * @param string $awsBucket AWS Bucket Name
     * @param string $bucketFilePrefix AWS Bucket File as prefix
     * @param string $fileFolderPrefix Destination folder name where file should be moved
     * @param boolean $deleteOldMedis True if delete file from Source folder else false
     * @param boolean $isInventory True if image is of inventory
     * @return string
     */
    static public function FileUploadToTempObject($file, $tempFolder, $appEnv, $awsAccessKeyId, $awsBucket, $bucketFilePrefix, $fileFolderPrefix = NULL, $deleteOldMedis = FALSE, $isInventory = FALSE) {
        try {
            $fileUrl = '';
            $path = app()->basePath('public/');
            if($isInventory) {
                goto storeFileToTempFolder;
            } else {
                if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                    storeFileToTempFolder:
                    $new_path = $path . $file;
                    if (isset($fileFolderPrefix)) {
                        $old_path = $path . $file;
                        $new_path = $path . str_replace($tempFolder . "/", $fileFolderPrefix . "/", $file);
                        if ($old_path != $new_path) {
                            copy($old_path, $new_path);
                        }
                        if ($deleteOldMedis) {
                            if ($old_path != $new_path) {
                                self::FileDeleteObject($file, $appEnv, $awsAccessKeyId, $awsBucket, $bucketFilePrefix, $isInventory);
                            }
                        }
                    }
                    if (file_exists($new_path)) {
                        // chmod($new_path, 0777);
                        exec('sudo chmod -R 777 ' . $new_path);
                    }
                    $fileUrl = (isset($fileFolderPrefix)) ? str_replace($tempFolder . "/", $fileFolderPrefix . "/", $file) : $file;
                } else {
                    if ($awsAccessKeyId != '') {
                        $new_path = (isset($fileFolderPrefix)) ? str_replace($tempFolder . "/", $fileFolderPrefix . "/", $file) : $file;
                        $s3 = AWS::createClient('s3');
                        $s3->putObject(array(
                            'Bucket' => $awsBucket,
                            'Key' => $new_path,
                            'SourceFile' => $path . $file,
                        ));
                        /* $fileUrl = $s3->getObjectUrl($awsBucket, $new_path); */
                        $fileUrl = $new_path;
                    }
                    if ($deleteOldMedis) {
                        if ($new_path != $file) {
                            self::FileDeleteObject($file, $appEnv, $awsAccessKeyId, $awsBucket, $bucketFilePrefix, $isInventory);
                        }
                    }
                }
            }
            return $fileUrl;
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for FileUploadToTempObject');
        }
    }

    /**
     * Get full file path based on environment
     * @param string $file Basic file name with folder name as prefix like (abc/bcd.ext)
     * @param string $appEnv Environment of Application
     * @param string $bucketFilePrefix AWS Bucket File as prefix
     * @param string $awsDefaultRegion Default region for AWS Bucket
     * @param string $awsBucket AWS Bucket Name
     * @param string $appHost Host for Application
     * @param boolean $isInventory True for Inventory images.
     * @param boolean $isVideo True for Video content
     * @param boolean $storeCache True if stored cache
     * @return string
     */
    static public function FileGetPath($file, $appEnv, $bucketFilePrefix, $awsDefaultRegion, $awsBucket, $appHost = '', $isInventory = false, $isVideo = false, $storeCache = FALSE) {
        try {
            /* if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
            } else {
                $protocol = "https://";
            }
            if(isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
                $domainName = $_SERVER['HTTP_HOST'];
            } else {
                $protocol = "";
                $domainName = rtrim($appHost, "/");
            } */
            if (trim($file) != '') {
                $filePrefix = rtrim($appHost, "/") . '/api/';
                if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                    $file = $filePrefix . trim($file);
                } else {
                    /* $s3 = AWS::createClient('s3');
                      $file = $s3->getObjectUrl($awsBucket, $file); */
                    if ($isInventory) {
                        $file = $filePrefix . trim($file);
                    } else {
                        if ($isVideo) {
                            $file = $bucketFilePrefix . trim($file);
                        } else {
                            // $file = rtrim($appHost, "/") . '/api/v1/s3/' . trim($file);
                            $file = (new S3WrapperController)->getActualFile(trim($file), $storeCache, $awsDefaultRegion, $awsBucket, $appHost);
                        }
                    }
                }
            }
            return trim($file);
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for FileGetPath');
        }
    }

    /**
     * Get file stored path from full url
     * @param string $file full file url with http_host
     * @param string $appEnv Environment of Application
     * @param string $appHost Host for Application
     * @param string $bucketFilePrefix AWS Bucket File as prefix
     * @return string
     */
    static public function FileGetDBEntry($file, $appEnv, $appHost, $bucketFilePrefix) {
        try {
            if (trim($file) != '') {
                if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                    /* $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
                    $domainName = $_SERVER['HTTP_HOST']; */
                    $filePrefix = rtrim($appHost, "/") . '/api/';
                    $file = ltrim(trim($file), $filePrefix);
                } else {
                    /* $s3 = AWS::createClient('s3');
                      $file = $s3->getObjectUrl($awsBucket, $file); */
                    $file = ltrim(trim($file), $bucketFilePrefix);
                }
            }
            return trim($file);
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for FileGetDBEntry');
        }
    }

    /**
     * Make File name Sorter
     * @param string $file Basic file name without extension
     * @return string
     */
    static public function FileNameTrim($file) {
        $newFileName = self::RemoveSpecialSymbol($file);
        $newFileName = (strlen(str_replace(' ', '_', $newFileName)) > 80) ? substr(str_replace(' ', '_', strtolower($newFileName)), 0, 80) : str_replace(' ', '_', strtolower($newFileName));
        return $newFileName;
    }

    /**
     * Download file from S3 bucket for live environment
     * @param string $file Basic file path
     * @param string $downloadFolderPath Only folder path or name where we need to store file without file-name like ("abc/xyz") which is in public folder
     * @param string $appEnv Environment of Application
     * @param string $awsBucket AWS Bucket Name
     * @param string $appHost Host for Application
     * @return string stored file path
     */
    static public function DownloadFileToLocal($file, $downloadFolderPath, $appEnv, $awsBucket, $appHost) {
        try {
            $file_url = $file_path = "";
            $dirName = app()->basePath('public/');
            if (trim($file) != '') {
                if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                    $old_path = $dirName . $file;
                    $new_path = $dirName . $downloadFolderPath . '/' . basename($file);
                    if ($old_path != $new_path) {
                        copy($old_path, $new_path);
                    }
                    if (file_exists($new_path)) {
                        // chmod($new_path, 0777);
                        exec('sudo chmod -R 777 ' . $new_path);
                    }
                } else {
                    $store_file_path = $dirName . $downloadFolderPath . '/' . basename($file);
                    if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                        copy($dirName . $file, $store_file_path);
                    } else {
                        $s3 = AWS::createClient('s3');
                        $files = $s3->getObject(array(
                            'Bucket' => $awsBucket,
                            'Key' => $file,
                            'SaveAs' => $store_file_path)
                        );
                    }
                    if (file_exists($store_file_path)) {
                        // chmod($store_file_path, 0777);
                        exec('sudo chmod -R 777 ' . $store_file_path);
                    }
                }
                if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                    /* $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
                    $domainName = $_SERVER['HTTP_HOST']; */
                    $filePrefix = rtrim($appHost, "/") . '/api/';
                } else {
                    $filePrefix = rtrim($appHost, "/") . '/api/';
                }
                $file_url = $filePrefix . trim($downloadFolderPath) . "/" . basename(trim($file));
                $file_path = $dirName . trim($downloadFolderPath) . "/" . basename(trim($file));
            }
            return array("url" => $file_url, "path" => $file_path);
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for DownloadFileToLocal');
        }
    }

    /**
     * 
     * @param array $files array of files which will be added in zip file
     * @param string $zip_name zipfile name with extension like (""demo.zip)
     * @param string $zip_folder Only folder path or name where we need to store file without file-name like ("abc/xyz") which is in public folder
     * @param boolean $type 1 = URLs and 0 = local folder path
     * @param string $appHost Host for Application
     * @return array zip path and url
     */
    static public function CreateZipFile($files, $zip_name, $zip_folder, $type = 0, $appHost) {
        try {
            $file_url = $file_path = "";
            if (count($files)) {
                $dirName = app()->basePath('public/');
                /* $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
                $domainName = $_SERVER['HTTP_HOST']; */
                $filePrefix = rtrim($appHost, "/") . '/api/';

                $zip = new \ZipArchive;
                if ($zip->open($dirName . $zip_folder . '/' . $zip_name, \ZipArchive::CREATE) === TRUE) {
                    foreach ($files as $attachment) {
                        if ($type) {
                            /* Download File from URL */
                            $download_file = file_get_contents($attachment);
                            $zip->addFromString(basename($attachment), $download_file);
                        } else {
                            $zip->addFile($attachment, basename($attachment));
                        }
                    }
                    $zip->close();
                }
                // chmod($dirName . $zip_folder . '/' . $zip_name, 0777);
                exec('sudo chmod -R 777 ' . $dirName . $zip_folder . '/' . $zip_name);
                $file_url = $filePrefix . trim($zip_folder) . "/" . trim($zip_name);
                $file_path = $dirName . trim($zip_folder) . "/" . trim($zip_name);
                foreach ($files as $attachment) {
                    if (file_exists($attachment)) {
                        $isRemoved = unlink($attachment);
                    }
                }
            }
            return array("url" => $file_url, "path" => $file_path);
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for CreateZipFile');
        }
    }

    /**
     * Check If file is available in AWS S3 Bucket
     * @param string $file Basic file name with folder name as prefix like (abc/bcd.ext)
     * @param string $appEnv Environment of Application
     * @param string $awsBucket AWS Bucket Name
     * @return boolean
     */
    static public function FileExists($file, $appEnv, $awsBucket) {
        try {
            if ($appEnv === 'local' || $appEnv === 'staging' || $appEnv === 'demo') {
                $path = app()->basePath('public/');
                $filePath = $path . $file;
                if (!file_exists($filePath)) {
                    return false;
                } else {
                    return self::FileGetPath($file, $appEnv, '', '', $awsBucket);
                }
            } else {
                if (trim($file) != '') {
                    $s3 = AWS::createClient('s3');
                    $files = $s3->doesObjectExist($awsBucket, $file);
                    $files = (isset($files) && !empty($files)) ? $files : false;
                    return $files;
                } else {
                    return false;
                }
            }
        } catch (\Exception $e) {
            Log::info("Backend error for FileExists : " . $e);
        }
    }

    /**
     * Random stging generate
     * @param integer $length Length of random string
     * @param array() $types multiple type like lower,upper,number Ex. ['lower','upper','number'] for all mixed and user can make combination of all three.
     * @return string
     */
    static public function RandomString($length = 10, $types = ['lower', 'number']) {
        $characters = "";
        foreach ($types as $type) {
            switch ($type) {
                case ("lower"):
                    $characters .= 'abcdefghijklmnopqrstuvwxyz';
                    break;
                case ("upper"):
                    $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    break;
                default :
                    $characters .= '0123456789';
            }
        }
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Make File name Sorter
     * @param string $file Basic file name without extension
     * @return string
     */
    static public function RemoveSpecialSymbol($file) {
        $FileArray = explode('/', $file);
        $fileNm = preg_replace("/[^a-zA-Z0-9s .\-\_]/", "", end($FileArray));
        array_pop($FileArray);
        $fileNew = '';
        foreach ($FileArray as $fnm) {
            $fileNew .= $fnm . '/';
        }
        $fileNew .= $fileNm;
        return $fileNew;
    }

    /**
     * Create Thumb File From Main Image
     * @param string $filePath Main file path
     * @param string $thumbFilePath Thumb file path
     * @param string $thumbWitdh Thumb file width
     * @param boolean $focus true if focus
     * @param string $quality Quality of thumbnail image
     * @param boolean $imageConvertGd true if converted image from url based on image format
     * @return Boolean true for success thumb create False for thumb creation fail.
     */
    static public function createThumbnail($filePath, $thumbFilePath, $thumbWitdh = 600, $focus = FALSE, $quality = 0.9, $imageConvertGd = FALSE) {
        try {
            /* getting extension from file path */
            $ext = explode('.',$filePath);
            $ext = end($ext);
            if (file_exists($filePath)) {
                /* getting cuttont image width and height */
                list($oldWidth, $oldHeight) = getimagesize($filePath);
                /* Calculationg thumb image width and height  */
                $newWidth = $thumbWitdh;
                if($oldWidth > 0) {
                    $newHeight = floor($oldHeight * ($thumbWitdh / $oldWidth));
                } else {
                    $newHeight = floor($oldHeight * $thumbWitdh);
                }

                if ($imageConvertGd) {
                    /* creating image from url based on image format */
                    switch(strtolower($ext)){
                        case "png":
                            $image = imagecreatefrompng($filePath);
                            break;
                        case "jpeg":
                            $image = imagecreatefromjpeg($filePath);
                            break;
                        case "jpg":
                            $image = imagecreatefromjpeg($filePath);
                            break;
                        case "gif":
                            $image = imagecreatefromgif($filePath);
                            break;
                        default:
                            $image = imagecreatefromjpeg($filePath);
                    }
                    /* getting cuttont image width and height */
                    $oldWidth = imagesx($image);
                    $oldHeight = imagesy($image);
                    /* Calculationg thumb image width and height  */
                    $newWidth = $thumbWitdh;
                    $newHeight = floor($oldHeight * ($thumbWitdh / $oldWidth));
                    
                    /* imagejpeg($image, $thumbFilePath, 90); */ //Simple reduce image quality and 90 is percentage

                    /* Resize image using GD start */
                    $name = imagecreatetruecolor($newWidth, $newHeight);
                    imagecopyresized($name, $image, 0,0,0,0,$newWidth,$newHeight,$oldWidth,$oldHeight);
                    imagejpeg($name, $thumbFilePath);
                    if (file_exists($thumbFilePath)) {
                        // chmod($thumbFilePath, 0777);
                        exec('sudo chmod -R 777 ' . $thumbFilePath);
                    }
                    /* Resize image using GD end */
                } else {
                    /* Resize image using image megic start */
                    $im = new \imagick($filePath);
                    if($focus){
                        $newWidth = floor($oldWidth * ($thumbWitdh /100));
                        if($oldWidth > 0) {
                            $newHeight = floor($oldHeight * ($thumbWitdh / 100));
                        } else {
                            $newHeight = $newWidth;
                        }
                        /* Log::info("Crop Applied for " . $focus . ", Image Width : " . $oldWidth ." Image Height : " . $oldHeight ." New Width : " . $resize_w ." New Height : " . $resize_h); */
                        $im->resizeImage($oldWidth,$oldHeight, \imagick::FILTER_LANCZOS, $quality, true);
                        switch ($focus) {
                            case 'northwest':
                                $im->cropImage($newWidth, $newHeight, 0, 0);
                                break;                    
                            case 'center':
                                $xaxis = ((($oldWidth - $newWidth) / 2) >= 0) ? ($oldWidth - $newWidth) / 2 : 0;
                                $yaxis = ((($oldHeight - $newHeight) / 2) >= 0) ? ($oldHeight - $newHeight) / 2 : 0;
                                $im->cropImage($newWidth, ($newHeight * 1.5), $xaxis, $yaxis);
                                break;
                            case 'northeast':
                                $im->cropImage($newWidth, $newHeight, $newWidth - $newWidth, 0);
                                break;
                            case 'southwest':
                                $im->cropImage($newWidth, $newHeight, 0, $newHeight - $newHeight);
                                break;
                            case 'southeast':
                                $im->cropImage($newWidth, $newHeight, $newWidth - $newWidth, $newHeight - $newHeight);
                                break;
                        }
                    } else {
                        $im->resizeImage($newWidth,$newHeight, \imagick::FILTER_LANCZOS, $quality, true);
                    }
                    $im->writeImage($thumbFilePath);
                    if (file_exists($thumbFilePath)) {
                        // chmod($thumbFilePath, 0777);
                        exec('sudo chmod -R 777 ' . $thumbFilePath);
                    }
                    /* Resize image using image megic end */
                }
                if (file_exists($thumbFilePath)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }            
        } catch (\Exception $e) {
            ExceptionHelper::ExceptionNotification($e, 'FileHandleHelper', 'Backend error for createThumbnail');
            return false;
        }
    }

}