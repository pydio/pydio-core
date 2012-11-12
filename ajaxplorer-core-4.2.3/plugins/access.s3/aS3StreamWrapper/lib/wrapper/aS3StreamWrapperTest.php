<?php 

// This is a standalone PHP script that tests the stream wrapper.
// There are no dependencies on Symfony or Apostrophe.

require './aS3StreamWrapper.class.php';

// YOU create aS3StreamWrapperTestSettings.php. It should
// look like this (use an S3 region that has read-after-write consistency, such as us-west-1):
//
// <?php
// $testCredentials = array('key' => 'your key id', 'secretKey' => 'your secret key', 'region' => 'us-west-1');
// $publicBucket = 'yourpublicbucket';
// $privateBucket = 'yourprivatebucket';
//
// ** DO NOT ** TEST WITH BUCKETS YOU CARE ABOUT! USE NEW BUCKET NAMES! This code may append additional strings
// to your bucket names to make more bucket names for tests of rename() and so on.
//
// If you don't have your keys yet, go make an Amazon Web Services account.
// Don't specify an acl setting as we test it both ways here.

require './aS3StreamWrapperTestSettings.php';

$wrapper = new aS3StreamWrapper();

// Files we make with this protocol default to private
$privateOptions = array_merge($testCredentials, array('protocol' => 's3private', 'acl' => AmazonS3::ACL_PRIVATE));
$wrapper->register($privateOptions);

// Files we make with this protocol default to public
$publicOptions = array_merge($testCredentials, array('protocol' => 's3public', 'acl' => AmazonS3::ACL_PUBLIC));
$wrapper->register($publicOptions);

$content = "This is some awesome content for you.";
$passCount = 0;
$failCount = 0;

echo("Cleaning up previous run\n");
@recursiveRemove("s3private://$privateBucket");
@recursiveRemove("s3public://$publicBucket");
@recursiveRemove("s3private://$privateBucket-aux");
@recursiveRemove("s3private://$privateBucket-aux2");

test(mkdir("s3private://$privateBucket"), true, "mkdir of private test bucket");
test(mkdir("s3public://$publicBucket"), true, "mkdir of public test bucket");

// Demonstrate that we have support for default ACLs at the protocol level
file_put_contents("s3public://$publicBucket/public.txt", $content);
test(file_get_contents("http://$publicBucket.s3.amazonaws.com/public.txt"), $content, "Fetched implicitly public file via web (specified at register time for protocol)");
file_put_contents("s3private://$privateBucket/private.txt", $content);
$result = @file_get_contents("http://$privateBucket.s3.amazonaws.com/private.txt");
test($result, false, "Cannot fetch implicitly private file  via web (specified at register time for protocol)");

// Demonstrate that we can override ACLs on a per-file basis with stream contexts
file_put_contents("s3private://$privateBucket/public.txt", $content, 0, stream_context_create(array('s3' => array('acl' => AmazonS3::ACL_PUBLIC))));
test(file_get_contents("http://$privateBucket.s3.amazonaws.com/public.txt"), $content, "Fetched explicitly public file via web (specified via stream_context_create)");
file_put_contents("s3public://$publicBucket/private.txt", $content, 0, stream_context_create(array('s3' => array('acl' => AmazonS3::ACL_PRIVATE))));
$result = @file_get_contents("http://$publicBucket.s3.amazonaws.com/private.txt");
test($result, false, "Cannot fetch explicitly private file via web (specified via stream_context_create)");

test(file_put_contents("s3private://$privateBucket/file.txt", $content), strlen($content), "file_put_contents in root dir");
test(file_get_contents("s3private://$privateBucket/file.txt"), $content, "file_get_contents in root dir");
$dir = opendir("s3private://$privateBucket");
test(!!$dir, true, "opendir returned something true");
if (!$dir)
{
  // Infinite loops can follow this, flunk the whole run
  exit(1);
}
$files = array();
while (($file = readdir($dir)) !== false)
{
  $files[] = $file;
}
// Returns nothing, no good test there really
closedir($dir);
test(count($files), 3, "three files in directory");
test(in_array("file.txt", $files), true, "a file in the directory is named file.txt");
test(unlink("s3private://$privateBucket/file.txt"), true, "unlink says it removed file.txt");
@test(file_get_contents("s3private://$privateBucket/file.txt"), false, "unlink really removed file.txt");

unlink("s3private://$privateBucket/public.txt");
unlink("s3private://$privateBucket/private.txt");

// Make sure files of length 0 are actually created
$out = fopen("s3private://$privateBucket/emptyfile.txt", "w");
fclose($out);
test(!!file_exists("s3private://$privateBucket/emptyfile.txt"), true, "creating an empty file works");
unlink("s3private://$privateBucket/emptyfile.txt");

$paths = array();

for ($i = 0; ($i < 3); $i++)
{
  for ($j = 0; ($j < 3); $j++)
  {
    for ($k = 0; ($k < 3); $k++)
    {
      $path = "s3private://$privateBucket/$i/$j/$k.txt";
      test(file_put_contents($path, $content), strlen($content), "Created $path");
      $paths[] = $path;
    }
  }
}

// All of the following should contain three items (in some cases subdirs, in others files)
// Test subdirs with and without the / suffix 

$folders = array('/', '/0', '/0/', '/0/1', '/0/1/');

foreach ($folders as $folder)
{
  $path = "s3private://$privateBucket$folder";
  $dir = opendir($path);
  test(!!$dir, true, "Something returned for opendir for $path");
  if (!$dir)
  {
    // Infinite loops can follow this, flunk the whole run
    exit(1);
  }
  $count = 0;
  while (($item = readdir($dir)) !== false)
  {
    echo("$item\n");
    $count++;
  }
  closedir($dir);
  test($count, 3, "3 items at $path");
}

shuffle($paths);
$path = $paths[0];
test(file_get_contents($path), $content, "Retrieved $path");

test(is_dir("s3private://$privateBucket/"), true, "bucket is a folder (with /)");
test(is_dir("s3private://$privateBucket"), true, "bucket is a folder (without /)");
test(is_dir("s3private://$privateBucket/0"), true, "/0 is a folder (without /)");
test(is_dir("s3private://$privateBucket/0/"), true, "/0/ is a folder (with /)");

$out = fopen("s3private://$privateBucket/newfile.txt", "w");
test(!!$out, true, "fopen returned something for writing to newfile.txt");
test(fwrite($out, $content), strlen($content), "fwrite returned correct # of bytes");
test(fwrite($out, $content), strlen($content), "fwrite returned correct # of bytes");
fclose($out);
test(is_dir("s3private://$privateBucket/newfile.txt"), false, "newfile.txt is not a folder");
$stat = stat("s3private://$privateBucket/newfile.txt");
test(is_array($stat), true, "stat of newfile.txt is an array");
test($stat['size'] === (strlen($content) + strlen($content)), true, "stat returns correct size for newfile.txt");
$in = fopen("s3private://$privateBucket/newfile.txt", "r");
test(!!$in, true, "fopen returned something for reading from newfile.txt");
test(feof($in), false, "Not at EOF of newfile.txt");
test(fread($in, strlen($content)), $content, "Received correct data from first fread of newfile.txt");
test(fread($in, strlen($content)), $content, "Received correct data from second fread of newfile.txt");
$third = fread($in, strlen($content));
test(fread($in, strlen($content)), '', "Received empty string from third fread of newfile.txt");
test(feof($in), true, "at EOF of newfile.txt");
fclose($in);

test(rename("s3private://$privateBucket/0", "s3private://$privateBucket/0renamed"), true, "Rename of /0 returned true");
$dir = opendir("s3private://$privateBucket/0renamed");
test(!!$dir, true, "Something returned for opendir for /0renamed");
if (!$dir)
{
  // Infinite loops can follow this, flunk the whole run
  exit(1);
}
$count = 0;
while (($item = readdir($dir)) !== false)
{
  // echo("$item\n");
  $count++;
}
closedir($dir);
test($count, 3, "3 items at /0renamed");
test(file_get_contents("s3private://$privateBucket/0renamed/0/0.txt"), $content, "/0renamed/0/0.txt has correct content");
test(file_exists("s3private://$privateBucket/0"), false, "/0 is gone");
test(file_exists("s3private://$privateBucket/0renamed"), true, "But /0renamed is not gone, file_exists works properly for folders");
test(rename("s3private://$privateBucket/2/2", "s3private://$privateBucket-aux"), true, "Renamed a folder to a bucket");
test(file_exists("s3private://$privateBucket-aux/0.txt"), true, "/0.txt exists in aux bucket");
test(rename("s3private://$privateBucket-aux", "s3private://$privateBucket-aux2"), true, "Bucket to bucket rename");
test(file_exists("s3private://$privateBucket-aux2/0.txt"), true, "/0.txt exists in aux2 bucket");
test(rename("s3private://$privateBucket-aux2/0.txt", "s3private://$privateBucket-aux2/renamed.txt"), true, "Plain old file rename");
test(file_exists("s3private://$privateBucket-aux2/renamed.txt"), true, "/renamed.txt exists in aux2 bucket");

test(recursiveRemove("s3private://$privateBucket"), true, "Recursively removed bucket and its contents");

echo("Tests passed: $passCount\n");
echo("Tests failed: $failCount\n");
if ($failCount)
{
  exit(1);
}
exit(0);

function test($result, $expected, $label)
{
  global $failCount;
  global $passCount;
  if ($result !== $expected)
  {
    echo("$label FAILED got $result, expected $expected\n");
    $failCount++;
  }
  else
  {
    echo("$label PASSED\n");
    $passCount++;
  }
}

// This is a straight recursive remove using the standard file functions, which should work
// if the stream wrapper is correctly implemented. There's a check here to make sure we don't
// stray from the s3private:// protocol when removing files

function recursiveRemove($path)
{
  // Ensure trailing / so we can append
  if (!preg_match('/\/$/', $path))
  {
    $path .= '/';
  }
  $dir = opendir($path);
  if (!$dir)
  {
    return false;
  }
  while (($item = readdir($dir)) !== false)
  {
    if (($item === '.') || ($item === '..'))
    {
      continue;
    }
    $itemPath = $path . $item;
    if (is_dir($itemPath))
    {
      recursiveRemove($itemPath);
    }
    else
    {
      if (substr($itemPath, 0, 2) !== 's3')
      {
        test(false, true, "Paths being unlinked should start with s3, something scary is wrong, bailing out");
        exit(1);
      }
      if (!unlink($itemPath))
      {
        return false;
      }
    }
  }
  closedir($dir);
  rmdir($path);
  return true;
}
