<?php

class OA_Permission_User {
  public $aUser = null;
  public $aAccount = null;
}

/**
 * Configure App specific behavior for 
 * Maestrano SSO
 */
class MnoSsoUser extends MnoSsoBaseUser
{
  /**
   * Database connection
   * @var PDO
   */
  public $connection = null;
  
  
  /**
   * Extend constructor to inialize app specific objects
   *
   * @param OneLogin_Saml_Response $saml_response
   *   A SamlResponse object from Maestrano containing details
   *   about the user being authenticated
   */
  public function __construct(OneLogin_Saml_Response $saml_response, &$session = array(), $opts = array())
  {
    // Call Parent
    parent::__construct($saml_response,$session);
    
    // Assign new attributes
    $this->connection = $opts['db_connection'];
  }
  
  
  /**
   * Sign the user in the application. 
   * Parent method deals with putting the mno_uid, 
   * mno_session and mno_session_recheck in session.
   *
   * @return boolean whether the user was successfully set in session or not
   */
  protected function setInSession()
  {
    // Cleanup old sessions (older than 10 days)
    $q = "DELETE FROM ox_session WHERE lastused < ?";
    $stmt = $this->connection->prepare($q);
    $stmt->bind_param('s', Array(date("Y-m-d H:i:s", strtotime('-10 days', time())))[0]);
    $stmt->execute();
    $stmt->close();
    
    
    // Get user and default account
    if ($this->local_id) {
      $q = "SELECT * FROM ox_users WHERE user_id = $this->local_id";
      $user = $this->connection->query($q)->fetch_assoc();
      
      $q = "SELECT * FROM ox_accounts WHERE account_id = {$user['default_account_id']}";
      $account = $this->connection->query($q)->fetch_assoc();
    }
    
    // Log user in
    if ($user && $account) {
      $session = Array();
      
      $session['lastused'] = date("Y-m-d H:i:s", time());
      $session['id'] = md5(uniqid('phpads', 1));
      
      $data = new OA_Permission_User;
      $data->aUser = $user;
      $data->aAccount = $account;
      
      $session['data'] = Array(
        'user' => $data
      );
        
      // Create session
      $q = "INSERT INTO ox_session(
        sessionid,
        sessiondata,
        lastused)
        VALUES(?,?,?)";
      $stmt = $this->connection->prepare($q);
      $stmt->bind_param('sss', $session['id'], serialize($session['data']), $session['lastused']);
      $stmt->execute();
      $stmt->close();
        
      setcookie('sessionID', $session['id'], 0, '/');
        
      return true;
    } else {
        return false;
    }
  }
  
  /**
   * Used by createLocalUserOrDenyAccess to create a local user 
   * based on the sso user.
   * If the method returns null then access is denied
   *
   * @return the ID of the user created, null otherwise
   */
  protected function createLocalUser()
  {
    $lid = null;
    
    if ($this->accessScope() == 'private') {
      // First build the user
      $user = $this->buildLocalUser();
      
      // Create user
      $query = "INSERT INTO ox_users(
        contact_name,
        email_address,
        username,
        password,
        language,
        default_account_id,
        active,
        date_created,
        email_updated) 
      VALUES(?,?,?,MD5(?),?,?,?,?,?)";
      $stmt = $this->connection->prepare($query);
      
      $stmt->bind_param('sssssiiss', 
        $user['contact_name'],
        $user['email_address'],
        $user['username'],
        $user['password'],
        $user['language'],
        $user['default_account_id'],
        $user['active'],
        $user['date_created'],
        $user['email_updated']
      );
      
      
      $stmt->execute();
      $lid = $stmt->insert_id;
      $stmt->close();
      var_dump($lid);
    }
    
    return $lid;
  }
  
  /**
   * Build a local user for creation
   *
   * @return hash with user attributes
   */
  protected function buildLocalUser()
  {
    $user_data = Array(
      'username'           => $this->uid,
      'email_address'      => $this->email,
      'contact_name'       => "$this->name $this->surname",
      'password'           => $this->generatePassword(),
      'language'           => 'en',
      'default_account_id' => 1,
      'active'             => 1,
      'date_created'       => date("Y-m-d H:i:s", time()),
      'email_updated'      => date("Y-m-d H:i:s", time()),
    );
    
    return $user_data;
  }
  
  /**
   * Get the ID of a local user via email lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByUid()
  {
    $arg = $this->connection->escape_string($this->uid);
    $query = "SELECT user_id FROM ox_users WHERE mno_uid = '$arg'";
    $result = $this->connection->query($query);
    $result = $result->fetch_assoc();
    
    if ($result && $result['user_id']) {
      return $result['user_id'];
    }
    
    return null;
    
    return null;
  }
  
  /**
   * Get the ID of a local user via email lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByEmail()
  {
    $arg = $this->connection->escape_string($this->email);
    $query = "SELECT user_id FROM ox_users WHERE email_address = '$arg'";
    $result = $this->connection->query($query);
    $result = $result->fetch_assoc();
    
    if ($result && $result['user_id']) {
      return $result['user_id'];
    }
    
    return null;
  }
  
  /**
   * Set all 'soft' details on the user (like name, surname, email)
   * Implementing this method is optional.
   *
   * @return boolean whether the user was synced or not
   */
   protected function syncLocalDetails()
   {
     if($this->local_id) {
       $query = "UPDATE ox_users SET username = ?, email_address = ?, contact_name = ? WHERE user_id = ?";
       $stmt = $this->connection->prepare($query);
       $stmt->bind_param("sssi", 
         $this->uid,
         $this->email,
         Array("$this->name $this->surname")[0],
         $this->local_id);
       $upd = $stmt->execute();
       $stmt->close();
       
       return $upd;
     }
     
     return false;
   }
  
  /**
   * Set the Maestrano UID on a local user via id lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function setLocalUid()
  {
    if($this->local_id) {
      $query = "UPDATE ox_users SET mno_uid = ? WHERE user_id = ?";
      $stmt = $this->connection->prepare($query);
      $stmt->bind_param("si", $this->uid, $this->local_id);
      $upd = $stmt->execute();
      $stmt->close();
      
      return $upd;
    }
    
    return false;
  }
}