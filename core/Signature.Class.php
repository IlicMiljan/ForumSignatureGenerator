<?php

/**
 *
 */

class Signature
{

  public $Status   = NULL; // Status Message.
  public $BoardURL = NULL; // Board URL.
  public $Username = NULL; // Account Username.
  public $Password = NULL; // Account Password.

  public $HTML     = NULL; // Store downloaded User profile HTML.
  public $DOM      = NULL; // Store initialized DOM Document.
  public $Finder   = NULL; // Store initialized element Finder.

  function __construct($BoardURL, $Username, $Password)
  {
    $this->BoardURL = $BoardURL; // Assign BoardURL variable.
    $this->Username = $Username; // Assign Username variable.
    $this->Password = $Password; // Assign Password variable.
  }

  private function CheckUserURL($UserURL) { // Check if UserURL is valid.

    $CheckURL = parse_url($this->BoardURL);

    if(strpos($UserURL, $CheckURL['host']) == FALSE || strpos($UserURL, "member.php") == FALSE) // Check does UserURL contains valid route parameters.
    {
      $this->Status = 'Please enter a valid URL of your Board Profile!'; // Set Status Message.
      return FALSE; // If route is not valid return FALSE.
    }

    return TRUE; // If all is OK, return TRUE.

  }

  private function GetHTML($UserURL) { // Login to Board and download user profile HTML.

    $CH = curl_init();
    curl_setopt($CH, CURLOPT_HEADER, FALSE);
    curl_setopt($CH, CURLOPT_COOKIEFILE, 'core/Cookie.txt');
    curl_setopt($CH, CURLOPT_COOKIEJAR, 'core/Cookie.txt');
    curl_setopt($CH, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($CH, CURLOPT_COOKIESESSION, TRUE);
    curl_setopt($CH, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($CH, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($CH, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($CH, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($CH, CURLOPT_REFERER, $this->BoardURL . 'index.php');
    curl_setopt($CH, CURLOPT_URL, $this->BoardURL . 'login.php?do=login');
    curl_setopt($CH, CURLOPT_POST, 1);
    curl_setopt($CH, CURLOPT_POSTFIELDS, "vb_login_username=$this->Username&vb_login_password&s=&securitytoken=guest&do=login&vb_login_md5password=".md5($this->Password)."&vb_login_md5password_utf=".md5($this->Password));
    curl_exec($CH);
    curl_setopt($CH, CURLOPT_POST, 0);
    curl_setopt($CH, CURLOPT_REFERER, $this->BoardURL . 'login.php?do=login');
    curl_setopt($CH, CURLOPT_URL, $this->BoardURL . 'clientscript/vbulletin_global.js?v=373');
    curl_exec($CH);
    curl_setopt($CH, CURLOPT_REFERER, $this->BoardURL . 'login.php?do=login');
    curl_setopt($CH, CURLOPT_URL, $this->BoardURL . 'index.php');
    curl_exec($CH);
    curl_setopt($CH, CURLOPT_REFERER, $this->BoardURL . 'login.php?do=login');
    curl_setopt($CH, CURLOPT_URL, $UserURL);

    $this->HTML = curl_exec($CH);

    return TRUE; // If all is OK, return TRUE.

  }

  private function SetupDOM() {

    $this->DOM = @DOMDocument::loadHTML($this->HTML);
    return TRUE; // If all is OK, return TRUE.

  }

  private function SetupFinder() {

    $this->Finder = new DomXPath($this->DOM);
    return TRUE; // If all is OK, return TRUE.

  }

  private function GetTitle() {

    $TitleSelect = $this->DOM->getElementsByTagName('title');
    return trim(@$TitleSelect->item(0)->nodeValue);

  }

  private function GetUsername() {

    $UsernameSelect = $this->Finder->query("//*[contains(@class, 'member_username')]");
    return trim(@$UsernameSelect->item(0)->nodeValue);

  }

  private function GetStats() {

    $SelectStats = $this->Finder->query("//*[contains(@class, 'blockrow stats')]");

    foreach($SelectStats as $Container) {
      if(strpos($Container->nodeValue, 'Total Posts') !== FALSE) {
        $Stats['Posts'] = trim(preg_replace("/[^0-9]/", '', $Container->nodeValue));
      } elseif(strpos($Container->nodeValue, 'Join Date') !== FALSE) {
        $Stats['JoinDate'] = trim(preg_replace("/[^0-9\.]/", '', $Container->nodeValue));
      }
    }

    return $Stats;

  }

  private function GetLocation() {

    $Location = 'Not Set';
    $SelectLocation = $this->Finder->query("//*[contains(@class, 'userprof_content')]");

    foreach($SelectLocation as $Container) {
      $Elements = $Container->getElementsByTagName('dl');
      foreach($Elements as $Element) {
        if(strpos($Element->nodeValue, 'Location') !== FALSE) {
          $Location = $Element->nodeValue;
          $Location = str_replace("Location:", "", $Location);
        }
      }
    }

    return trim($Location);

  }

  private function GetReputation() {

    $Reputation = '0';
    $SelectReputation = $this->Finder->query("//*[contains(@class, 'member_reputation')]");

    foreach($SelectReputation as $Container) {
      $Elements = $Container->getElementsByTagName('img');
      foreach($Elements as $Element) {
        $Reputation++;
      }
    }

    return trim($Reputation);

  }

  public function CollectData($UserURL) {

    if($this->CheckUserURL($UserURL) == FALSE) {
      return FALSE;
    }

    $this->GetHTML($UserURL);
    $this->SetupDOM();
    $this->SetupFinder();

    $Data = array(
      "Title" => $this->GetTitle(),
      "Username" => $this->GetUsername(),
      "PostsCount" => $this->GetStats()["Posts"],
      "JoinDate" => $this->GetStats()["JoinDate"],
      "Location" => $this->GetLocation(),
      "Reputation" => $this->GetReputation()
    );

    return $Data;

  }

  private function RepPos($Reputation) {

    switch(strlen($Reputation)) {
      case '1' :
       $RepPos = '400';
      break;

      case '2' :
       $RepPos = '395';
      break;

      case '3' :
       $RepPos = '385';
      break;

      case '4' :
       $RepPos = '375';
      break;

      default:
       $RepPos = '400';
    }

    return $RepPos;

  }

  public function CreateImage($Data) {

    $PNGImage = imagecreatefrompng('images/BannerBG.png');
    $White = imagecolorallocate($PNGImage, 255, 255, 255);
    $FontPath = 'fonts/Comic.ttf';

    imagettftext($PNGImage, 11, 0, 120, 26, $White, $FontPath, 'Username:');
    imagettftext($PNGImage, 11, 0, 120, 46, $White, $FontPath, 'Total Posts:');
    imagettftext($PNGImage, 11, 0, 120, 66, $White, $FontPath, 'Join Date:');
    imagettftext($PNGImage, 11, 0, 120, 86, $White, $FontPath, 'Location:');

    imagettftext($PNGImage, 11, 0, 210, 26, $White, $FontPath, $Data['Username']);
    imagettftext($PNGImage, 11, 0, 210, 46, $White, $FontPath, $Data['PostsCount']);
    imagettftext($PNGImage, 11, 0, 210, 66, $White, $FontPath, $Data['JoinDate']);
    imagettftext($PNGImage, 11, 0, 210, 86, $White, $FontPath, $Data['Location']);

    imagettftext($PNGImage, 11, 0, 360, 36, $White, $FontPath, 'REPUTATION');
    imagettftext($PNGImage, 25, 0, $this->RepPos($Data['Reputation']), 75, $White, $FontPath, $Data['Reputation']);

    $SavePath = 'signatures'.DIRECTORY_SEPARATOR.$Data['Username'].'.png';

    imagepng($PNGImage, $SavePath);
    imagedestroy($PNGImage);

    return $SavePath;
  }

  public function CreateSignature($UserURL) {

    $Data     = $this->CollectData($UserURL);
    $SavePath = $this->CreateImage($Data);

    return $SavePath;

  }

  public function CreateDirectURL($SavePath) {

    $Protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';
    $URL = $Protocol.$_SERVER["SERVER_NAME"].dirname($_SERVER['SCRIPT_NAME'])."/".$SavePath;
    return $URL;
  }

}

?>
