<?php

namespace RoshaniSTPL\utility\Controllers;

use Illuminate\Http\Request;
use DateTime;
use Illuminate\Support\Facades\Cache;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class S3WrapperController {

    /**
     * 
     * @param Request $request
     * @return json
     */
    static public function getActualFile(Request $request, $params) {
        // $params = 'PostImages/1586510036_index-1.jpeg';
        $mimeTypeArr = ['.flv', '.mp4', '.m3u8', '.ts', '.3gp', '.mov', '.avi', '.wmv', '.webm', '.mp4#t=0.5'];
        $uniqueKey = md5($params);
        $explodeParams = explode("/", $params);        
        if(count($explodeParams) > 0) {
            /* $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
            $domainName = $_SERVER['HTTP_HOST']; */
            
            $date = new DateTime(date('Y-m-d'));
            $date->modify('+1 day');
            $next_day = $date->format('Y-m-d 03:00:00');
            $start_date = new DateTime(date('Y-m-d H:i:s'));
            $since_start = $start_date->diff(new DateTime($next_day));
            $minutes = $since_start->days * 24 * 60;
            $minutes += $since_start->h * 60;
            $minutes += $since_start->i;
            $store_cache = env('STORE_CACHE', false);

            if(Cache::has($uniqueKey)) {
                $cacheResult = Cache::get($uniqueKey);
                try {
                    // Display the object in the browser from Storage Folder.
                    /* $path = rtrim(app()->basePath('storage/app'), '/') . '/' . end($explodeParams);
                    $mimeType = mime_content_type($path);
                    header("Content-Type: {$mimeType}");
                    echo $cacheResult; */
                    
                    // Display the object in the browser from Public Folder.
                    $info = getimagesize($cacheResult);
                    header("Content-Type: {$info['mime']}");
                    echo file_get_contents($cacheResult);
                } catch (\Exception $e) {
                    return end($explodeParams);
                    // echo $e->getMessage();exit;
                }    
            } else {
                $s3 = new S3Client([
                    'version' => 'latest',
                    'region'  => env('AWS_DEFAULT_REGION')
                ]);
                
                try {
                    $extension = explode(".", end($explodeParams));
                    if(!in_array('.' . end($extension), $mimeTypeArr)) {
                        // Get the object.
                        $result = $s3->getObject([
                            'Bucket' => env('AWS_BUCKET'),
                            'Key'    => $params
                        ]);
                        if($store_cache) {
                            // Public Folder Coding
                            if (!file_exists(base_path('public') . "/S3Docs")) {
                                mkdir(base_path('public') . "/S3Docs", 0777, true);
                            }
                            file_put_contents('S3Docs/'.end($explodeParams), $result['Body']);
                            $path = rtrim(env('APP_HOST',""), "/") . '/api/S3Docs/' . end($explodeParams);
                        
                            // Storage Folder Coding
                            /* Storage::disk('local')->put(end($explodeParams), $result['Body']);
                            $contents = Storage::disk('local')->get(end($explodeParams)); */
                        
                            // Cache::add($uniqueKey, $contents, $minutes); // Storage
                            Cache::add($uniqueKey, $path, $minutes); // public
                        }
                        // Display the object in the browser.
                        header("Content-Type: {$result['ContentType']}");
                        echo $result['Body'];
                    } else {
                        // $params = "PostImages/1586846379_videoplayback.mp4";
                        $object = $s3->headObject([
                            'Bucket' => env('AWS_BUCKET'),
                            'Key'    => $params,
                        ]);
                        $filePath = $object['@metadata']['effectiveUri'];
                        $stream = "";
                        $buffer = 102400;
                        $start  = -1;
                        $end    = -1;
                        $size   = 0;
                        if (!($stream = fopen($filePath, 'rb'))) {
                            die('Could not open stream for reading');
                        }
                        ob_get_clean();
                        header("Content-Type: ".$object['ContentType']);
                        header("Cache-Control: max-age=2592000, public");
                        header("Expires: ".gmdate('D, d M Y H:i:s', time()+2592000) . ' GMT');
                        header("Last-Modified: ".gmdate('D, d M Y H:i:s', @filemtime($filePath)) . ' GMT' );
                        $start = 0;
                        $size = $object['ContentLength'];
                        $end = $size - 1;                        
                        header("Accept-Ranges: 0-".$end);

                        if (isset($_SERVER['HTTP_RANGE'])) {
                        } else {
                            header("Content-Length: ".$size);
                        }  
                        $i = $start;
                        set_time_limit(0);
                        while(!feof($stream) && $i <= $end) {
                            $bytesToRead = $buffer;
                            if(($i+$bytesToRead) > $end) {
                                $bytesToRead = $end - $i + 1;
                            }
                            $data = fread($stream, $bytesToRead);
                            echo $data;
                            flush();
                            $i += $bytesToRead;
                        }
                        fclose($stream);
                        exit;
                    }
                    
                } catch (S3Exception $e) {
                    // return end($explodeParams);
                    echo $e->getMessage() . PHP_EOL;
                }
            }
        } else {
            echo "Something went wrong.";
        }
    }
}