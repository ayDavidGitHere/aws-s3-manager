<?php

/**
 * S3Manager Class
 *
 * This class provides functionality to manage objects like files and folders in an AWS S3 bucket.
 * 
 * @author Ayoade David <https://github.com/aydavidgithere>
 */

namespace S3Manager;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;



class S3Manager {
    private $s3Client;
    private $bucketname;

    public function __construct($awsKey, $awsSecretKey, $region, $bucketname, $version = 'latest') {
        $this->s3Client = new S3Client([
            'version' => $version,
            'region' => $region,
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecretKey,
            ],
        ]);
        $this->setOperatingBucket($bucketname);
    }

    private function createBaseParam() {
        return [
            'Bucket' => $this->bucketname,
        ];
    }

    private function correctParams($params) {
        if(isset($params['Prefix'])) {
            $params['Prefix'] = str_ireplace("\'", "'", $params['Prefix']);  
        }
        if(isset($params['StartAfter'])) {
            $params['StartAfter'] = str_ireplace("\'", "'", $params['StartAfter']);  
        }
        if(isset($params['FilterByText'])) {
            $params['FilterByText'] = str_ireplace("\'", "'", $params['FilterByText']); 
        }
        return $params;
    }

    private function getObjectsContent($objects, $text = "") {
        return $this->filterContents($objects['Contents'] ?? [], $text);
    }

    private function filterContents($object_contents, $text) {
        $contents = [];
        $filterByText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        foreach ($object_contents as $object) {
            $object_key = $object['Key'];
            if (substr($object_key, -1) === '/') {
                continue;
            }
            if (substr(strrchr($object_key, '/'), 1, 1) === '.') {
                continue;
            }
            $tmp = explode('/', $object_key);
            if (!(stripos(end($tmp), $filterByText) !== false)) {
                continue; // Filter By Text
            }
            $contents[] = $object_key;
        }
        return $contents;
    }

    private function getObjectsSize($objects) {
        $totalSize = 0;
        foreach ($objects as $object) {
            $totalSize += $object['Size'];
        }
        return $totalSize;
    }

    private function getCommonPrefixes($objects) {
        $CommonPrefixes = [];
        foreach ($objects['CommonPrefixes'] ?? [] as $cp) {
            $CommonPrefixes[] = $cp['Prefix'];
        }
        return $CommonPrefixes;
    }

    private function getCommonPrefixesFromContent($content) {
        $subfoldernames = [];
        foreach ($content as $key) {
            $folders = explode('/', $key);
            if (count($folders) > 1) {
                $subfolder = $folders[1] . '/';
                if (!in_array($subfolder, $subfoldernames)) {
                    array_push($subfoldernames, $subfolder);
                }
            }
        }
        return $subfoldernames;
    }





    public function setOperatingBucket($bucketname) {
        $this->bucketname = $bucketname;
    }

    public function getBuckets() {
        $buckets = $this->s3Client->listBuckets();
        return $buckets['Buckets'];
    }

    public function createFile($folder, $fileName, $content) { 
        $this->s3Client->putObject([
            'Bucket' => $this->bucketname,
            'Key' => rtrim($folder, '/') . '/' . ltrim($fileName, '/'),
            'Body' => $content
        ]);
        return true; 
    }

    public function deleteFile($folder, $fileName) {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucketname,
                'Key' => rtrim($folder, '/') . '/' . ltrim($fileName, '/')
            ]);
            return true;
        } catch (AwsException $e) {
            // Handle error
            return false;
        }
    } 

    public function moveFile($sourceFolder, $sourceFileName, $destinationFolder, $destinationFileName) { 
        $sourceKey = rtrim($sourceFolder, '/') . '/' . ltrim($sourceFileName, '/');
        $destinationKey = rtrim($destinationFolder, '/') . '/' . ltrim($destinationFileName, '/');

        // Copy the file to the destination
        $this->s3Client->copyObject([
            'Bucket' => $this->bucketname,
            'CopySource' => $this->bucketname . '/' . $sourceKey,
            'Key' => $destinationKey
        ]);

        // Delete the file from the source
        $this->deleteFile($sourceFolder, $sourceFileName);

        return true; 
    } 

    public function getFolderData($foldername, $maxItemslength = 0, $startafter = "", $filterByText = "") {
        $s3Client = $this->s3Client;
        $listObjectsParams = array_merge($this->createBaseParam(), [
            'Prefix' => $foldername . '/',
            'StartAfter' => $startafter,
            'MaxKeys' => $maxItemslength,
        ]); 
        $listObjectsParams = $this->correctParams($listObjectsParams);
        $objects = $this->s3Client->listObjectsV2($listObjectsParams);

        $folderContents = $this->getObjectsContent($objects, $filterByText);
        return (object) [ 
            "contents" => $folderContents, 
            "contents_names" => $this->getCommonPrefixes($objects),
            "filescountwithoutfilter" => count($objects['Contents'] ?? []), 
            "total_size" => $this->getObjectsSize($objects),
        ];
    }

    public function getFileDownloadUrl($filekey, $valid_period = "+7 days") {
        $s3Client = $this->s3Client;

        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $this->bucketname,
            'Key' => $filekey,
            'ResponseContentDisposition' => urlencode("attachment; filename={$filekey}"),
        ]);
        $request = $s3Client->createPresignedRequest($cmd, $valid_period);
        $urlValue = (string) $request->getUri();

        return $urlValue;
    }

    public function getFolderDownloadUrls($foldername, $valid_period = "+7 days", $maxItemslength = 0, $startafter = "", $filterByText = "") {
        $s3Client = $this->s3Client;
        $listObjectsParams = array_merge($this->createBaseParam(), [
            'Prefix' => $foldername . '/',
            'StartAfter' => $startafter,
            'MaxKeys' => $maxItemslength,
        ]);
        $listObjectsParams = $this->correctParams($listObjectsParams);
        $objects = $this->s3Client->listObjectsV2($listObjectsParams);

        $urlsAsync = [];
        foreach ($this->getObjectsContent($objects) as $object) {
            $link = $this->getFileDownloadUrl($object['Key'], $valid_period);

            $urlsAsync[$object['Key']] = $link;
        }

        return $urlsAsync;
    }


    public function deleteFolder($foldername){  
        $s3Client = $this->s3Client;
        $listObjectsParams = array_merge($this->createBaseParam(), [
            'Prefix' => $foldername . '/', 
        ]);
        $listObjectsParams = $this->correctParams($listObjectsParams);
        $objects = $this->s3Client->listObjectsV2($listObjectsParams);

        // Delete each object within the folder
        foreach ($objects['Contents'] as $object) {
            $this->deleteFile($foldername, $object['Key']);
        }

        // If there are common prefixes (subfolders), recursively delete them
        foreach ($objects['CommonPrefixes'] as $prefix) {
            $this->deleteFolder($prefix['Prefix']);
        }

        return true;
    }

}
