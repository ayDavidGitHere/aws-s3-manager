# S3Manager Class

The S3Manager class aims to conveniently manage the objects in an AWS S3 bucket as if they are folders and files.

Please note that this class is largely unfinished. Pending operations to be implemented are listed in the file "operations.md".

## Public Methods

- `__construct($awsKey, $awsSecretKey, $region, $bucketname, $version = 'latest')`: Initializes the S3Manager instance with the provided AWS credentials and bucket information.
- `setOperatingBucket($bucketname)`: Sets the operating bucket for subsequent operations.
- `getBuckets()`: Retrieves a list of all available buckets in the AWS account.
- `createFile($folder, $fileName, $content)`: Creates a new file with the specified name and content in the specified folder.
- `deleteFile($folder, $fileName)`: Deletes the file with the specified name from the specified folder.
- `moveFile($sourceFolder, $sourceFileName, $destinationFolder, $destinationFileName)`: Moves a file from the source folder to the destination folder with the specified names.
- `getFolderData($foldername, $valid_period = "+7 days", $maxItemslength = 0, $startafter = "", $filterByText = "")`: Retrieves data about the contents of a folder, including files, subfolders, and their properties.
- `getFileDownloadUrl($filekey, $valid_period = "+7 days")`: Generates a pre-signed URL for downloading the file with the specified key.
- `getFolderDownloadUrls($foldername, $valid_period = "+7 days", $maxItemslength = 0, $startafter = "", $filterByText = "")`: Retrieves pre-signed URLs for downloading files within the specified folder.
- `deleteFolder($foldername, $valid_period = "+7 days", $maxItemslength = 0, $startafter = "", $filterByText = "")`: Deletes the folder and all its contents recursively.
