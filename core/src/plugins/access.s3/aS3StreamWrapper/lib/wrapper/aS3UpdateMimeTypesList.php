<?php

// You don't need this unless you are unsatisfied with our mime.types list and you have a better one.
// Please pipe me to aS3StreamWrapperMimeTypes.class.php. I don't work unless there is a mime.types file in the current folder.
// One can also subclass the stream wrapper and override getMimeType().

$lines = file('mime.types');

$mimeMap = array();
foreach ($lines as $line)
{
  if (preg_match('/([\w\-]+\/[\w\-]+)\s+([\w ]+)\s*$/', $line, $matches))
  {
    $type = $matches[1];
    $extensions = $matches[2];
    $extensions = preg_split('/\s+/', $extensions);
    foreach ($extensions as $extension)
    {
      $extension = trim($extension);
      if (strlen($extension))
      {
        $mimeMap[$extension] = $type;
      }
    }
  }
}

echo("<?php\n\n");
echo("class aS3StreamWrapperMimeTypes\n");
echo("{\n");
echo("  static public \$mimeTypes = array(\n");
foreach ($mimeMap as $extension => $type)
{
  echo("    '$extension' => '$type',\n");
}
echo("  );\n");
echo("}\n");
