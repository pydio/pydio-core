<?php
class EtherpadLiteClient
{
  const API_VERSION             = '1.1';

  const CODE_OK                 = 0;
  const CODE_INVALID_PARAMETERS = 1;
  const CODE_INTERNAL_ERROR     = 2;
  const CODE_INVALID_FUNCTION   = 3;
  const CODE_INVALID_API_KEY    = 4;

  protected $apiKey = "";
  protected $baseUrl = "http://localhost:9001/api";

  public function __construct($apiKey, $baseUrl = null)
  {
    if (strlen($apiKey) < 1) {
      throw new InvalidArgumentException("[{$apiKey}] is not a valid API key");
    }
    $this->apiKey  = $apiKey;

    if (isset($baseUrl)) {
      $this->baseUrl = $baseUrl;
    }
    if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
      throw new InvalidArgumentException("[{$this->baseUrl}] is not a valid URL");
    }
  }

  protected function get($function, array $arguments = array())
  {
    return $this->call($function, $arguments, 'GET');
  }

  protected function post($function, array $arguments = array())
  {
    return $this->call($function, $arguments, 'POST');
  }

  protected function call($function, array $arguments = array(), $method = 'GET')
  {
    $arguments['apikey'] = $this->apiKey;
    $arguments = http_build_query($arguments, '', '&');
    $url = $this->baseUrl."/".self::API_VERSION."/".$function;
    if ($method !== 'POST') {
      $url .=  "?".$arguments;
    }
    // use curl of it's available
    if (function_exists('curl_init')) {
      $c = curl_init($url);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($c, CURLOPT_TIMEOUT, 20);
      if ($method === 'POST') {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $arguments);
      }
      $result = curl_exec($c);
      curl_close($c);
    // fallback to plain php
    } else {
      $params = array('http' => array('method' => $method, 'ignore_errors' => true, 'header' => 'Content-Type:application/x-www-form-urlencoded'));
      if ($method === 'POST') {
        $params['http']['content'] = $arguments;
      }
      $context = stream_context_create($params);
      $fp = fopen($url, 'rb', false, $context);
      $result = $fp ? stream_get_contents($fp) : null;
    }

    if (!$result) {
      throw new UnexpectedValueException("Empty or No Response from the server");
    }

    $result = json_decode($result);
    if ($result === null) {
      throw new UnexpectedValueException("JSON response could not be decoded");
    }
    return $this->handleResult($result);
  }

  protected function handleResult($result)
  {
    if (!isset($result->code)) {
      throw new RuntimeException("API response has no code");
    }
    if (!isset($result->message)) {
      throw new RuntimeException("API response has no message");
    }
    if (!isset($result->data)) {
      $result->data = null;
    }

    switch ($result->code) {
      case self::CODE_OK:
        return $result->data;
      case self::CODE_INVALID_PARAMETERS:
      case self::CODE_INVALID_API_KEY:
        throw new InvalidArgumentException($result->message);
      case self::CODE_INTERNAL_ERROR:
        throw new RuntimeException($result->message);
      case self::CODE_INVALID_FUNCTION:
        throw new BadFunctionCallException($result->message);
      default:
        throw new RuntimeException("An unexpected error occurred whilst handling the response");
    }
  }

  // GROUPS
  // Pads can belong to a group. There will always be public pads that doesnt belong to a group (or we give this group the id 0)

  // creates a new group
  public function createGroup()
  {
    return $this->post("createGroup");
  }

  // this functions helps you to map your application group ids to etherpad lite group ids
  public function createGroupIfNotExistsFor($groupMapper)
  {
    return $this->post("createGroupIfNotExistsFor", array(
      "groupMapper" => $groupMapper
    ));
  }

  // deletes a group
  public function deleteGroup($groupID)
  {
    return $this->post("deleteGroup", array(
      "groupID" => $groupID
    ));
  }

  // returns all pads of this group
  public function listPads($groupID)
  {
    return $this->get("listPads", array(
      "groupID" => $groupID
    ));
  }

  // creates a new pad in this group
  public function createGroupPad($groupID, $padName, $text)
  {
    return $this->post("createGroupPad", array(
      "groupID" => $groupID,
      "padName" => $padName,
      "text"    => $text
    ));
  }

  // list all groups
  public function listAllGroups()
  {
    return $this->get("listAllGroups");
  }

  // AUTHORS
  // Theses authors are bind to the attributes the users choose (color and name).

  // creates a new author
  public function createAuthor($name)
  {
    return $this->post("createAuthor", array(
      "name" => $name
    ));
  }

  // this functions helps you to map your application author ids to etherpad lite author ids
  public function createAuthorIfNotExistsFor($authorMapper, $name)
  {
    return $this->post("createAuthorIfNotExistsFor", array(
      "authorMapper" => $authorMapper,
      "name"         => $name
    ));
  }

  // returns the ids of all pads this author as edited
  public function listPadsOfAuthor($authorID)
  {
    return $this->get("listPadsOfAuthor", array(
      "authorID" => $authorID
    ));
  }

  // Gets an author's name
  public function getAuthorName($authorID)
  {
    return $this->get("getAuthorName", array(
      "authorID" => $authorID
    ));
  }

  // SESSIONS
  // Sessions can be created between a group and a author. This allows
  // an author to access more than one group. The sessionID will be set as
  // a cookie to the client and is valid until a certian date.

  // creates a new session
  public function createSession($groupID, $authorID, $validUntil)
  {
    return $this->post("createSession", array(
      "groupID"    => $groupID,
      "authorID"   => $authorID,
      "validUntil" => $validUntil
    ));
  }

  // deletes a session
  public function deleteSession($sessionID)
  {
    return $this->post("deleteSession", array(
      "sessionID" => $sessionID
    ));
  }

  // returns informations about a session
  public function getSessionInfo($sessionID)
  {
    return $this->get("getSessionInfo", array(
      "sessionID" => $sessionID
    ));
  }

  // returns all sessions of a group
  public function listSessionsOfGroup($groupID)
  {
    return $this->get("listSessionsOfGroup", array(
      "groupID" => $groupID
    ));
  }

  // returns all sessions of an author
  public function listSessionsOfAuthor($authorID)
  {
    return $this->get("listSessionsOfAuthor", array(
      "authorID" => $authorID
    ));
  }

  // PAD CONTENT
  // Pad content can be updated and retrieved through the API

  // returns the text of a pad
  public function getText($padID, $rev=null)
  {
    $params = array("padID" => $padID);
    if (isset($rev)) {
      $params["rev"] = $rev;
    }
    return $this->get("getText", $params);
  }

  // returns the text of a pad as html
  public function getHTML($padID, $rev=null)
  {
    $params = array("padID" => $padID);
    if (isset($rev)) {
      $params["rev"] = $rev;
    }
    return $this->get("getHTML", $params);
  }

  // sets the text of a pad
  public function setText($padID, $text)
  {
    return $this->post("setText", array(
      "padID" => $padID,
      "text"  => $text
    ));
  }

  // sets the html text of a pad
  public function setHTML($padID, $html)
  {
    return $this->post("setHTML", array(
      "padID" => $padID,
      "html"  => $html
    ));
  }

  // PAD
  // Group pads are normal pads, but with the name schema
  // GROUPID$PADNAME. A security manager controls access of them and its
  // forbidden for normal pads to include a $ in the name.

  // creates a new pad
  public function createPad($padID, $text)
  {
    return $this->post("createPad", array(
      "padID" => $padID,
      "text"  => $text
    ), 'POST');
  }

  // returns the number of revisions of this pad
  public function getRevisionsCount($padID)
  {
    return $this->get("getRevisionsCount", array(
      "padID" => $padID
    ));
  }

  // returns the number of users currently editing this pad
  public function padUsersCount($padID)
  {
    return $this->get("padUsersCount", array(
      "padID" => $padID
    ));
  }

  // return the time the pad was last edited as a Unix timestamp
  public function getLastEdited($padID)
  {
    return $this->get("getLastEdited", array(
      "padID" => $padID
    ));
  }

  // deletes a pad
  public function deletePad($padID)
  {
    return $this->post("deletePad", array(
      "padID" => $padID
    ));
  }

  // returns the read only link of a pad
  public function getReadOnlyID($padID)
  {
    return $this->get("getReadOnlyID", array(
      "padID" => $padID
    ));
  }

  // returns the ids of all authors who've edited this pad
  public function listAuthorsOfPad($padID)
  {
    return $this->get("listAuthorsOfPad", array(
      "padID" => $padID
    ));
  }

  // sets a boolean for the public status of a pad
  public function setPublicStatus($padID, $publicStatus)
  {
    if (is_bool($publicStatus)) {
      $publicStatus = $publicStatus ? "true" : "false";
    }
    return $this->post("setPublicStatus", array(
      "padID"        => $padID,
      "publicStatus" => $publicStatus
    ));
  }

  // return true of false
  public function getPublicStatus($padID)
  {
    return $this->get("getPublicStatus", array(
      "padID" => $padID
    ));
  }

  // returns ok or a error message
  public function setPassword($padID, $password)
  {
    return $this->post("setPassword", array(
      "padID"    => $padID,
      "password" => $password
    ));
  }

  // returns true or false
  public function isPasswordProtected($padID)
  {
    return $this->get("isPasswordProtected", array(
      "padID" => $padID
    ));
  }

  // Get pad users
  public function padUsers($padID)
  {
    return $this->get("padUsers", array(
      "padID" => $padID
    ));
  }

  // Send all clients a message
  public function sendClientsMessage($padID, $msg)
  {
    return $this->post("sendClientsMessage", array(
      "padID" => $padID,
      "msg"   => $msg
    ));
  }
}
