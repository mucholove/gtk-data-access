<?php

// require_once("../DataAccess.php");


	/*
	passwordDoesNotMeetRequiredLength
	passwordTooLong
	passwordIsMissingUppercaseCharacter
	passwordIsMissingDigits
	*/
function validatePasswordIsSecure($password, $delegate = null)
{
	$debug = true;
	if ($debug)
	{
		if ($delegate)
		{
			error_log("Running - `validatePasswordIsSecure` with delegate: ".get_class($delegate));
		}
		else
		{
			error_log("`validatePasswordIsSecure` - No delegate.");
		}
	}
	global $_GLOBALS;
	if (!isset($_GLOBALS["APP_PASSWORD_REQUIREMENTS"]))
	{
		die('Need to set: `$_GLOBALS["APP_PASSWORD_REQUIREMENTS"])`');
	}
	$settings = $_GLOBALS["APP_PASSWORD_REQUIREMENTS"];

	// Validate password length
	if (strlen($password) < $settings['min_length'])
	{
		if ($debug)
		{
			error_log("`passwordDoesNotMeetRequiredLength`");
		}
		$delegate->passwordDoesNotMeetRequiredLength($settings['min_length']);
		return false;
	}
	if (strlen($password) > $settings['max_length'])
	{
		if ($debug)
		{
			error_log("`passwordTooLong`");
		}
		$delegate->passwordTooLong($settings['max_length']);
		return false;
	}

	// Validate character requirements
	if ($settings['require_uppercase'] && !preg_match('/[A-Z]/', $password)) 
	{
		
		/*
		if ($delegate && method_exists($delegate, "passwordIsMissingUppercaseCharacter"))
		{
			return $delegate->passwordIsMissingUppercaseCharacter();
		}
		*/
		if ($debug)
		{
			error_log("`passwordIsMissingUppercaseCharacter`");
		}
		$delegate->passwordIsMissingUppercaseCharacter();
		return false;
	}
	if ($settings['require_lowercase'] && !preg_match('/[a-z]/', $password)) 
	{
		if ($debug)
		{
			error_log(`passwordIsMissingLowercaseCharacter`);
		}
		$delegate->passwordIsMissingLowercaseCharacter();
		return false;
	}
	if ($settings['require_digits'] && !preg_match('/\d/', $password)) 
	{
		if ($debug)
		{
			error_log("`passwordIsMissingDigits`");
		}
		$delegate->passwordIsMissingDigits();
		return false;
	}
	if ($settings['require_special_chars'] && !preg_match($settings['special_chars'], $password))
	{
		if ($debug)
		{
			error_log("`passwordIsMissingSpecialCharacters`");
		}
		$delegate->passwordIsMissingSpecialCharacters($settings['special_chars']);
		return false;
	}

	return true;
}
	


class PersonaDataAccess extends DataAccess 
{
	
	public function rowStyleForItem($item, $index)
	{
		$style = "";

		$estado = $item["estado"];

		switch ($estado)
		{
			case 'activo':	
				$style .= "background-color:green;";
				break;
			case 'inactivo':
				$style .= "background-color:gray";
				break;
		}

		return $style;
	}
	public function injectVariablesForUserOnItem(&$user, &$item)
	{
		parent::injectVariablesForUserOnItem($user, $item);
		$item["created_by_user_id"] = $user["id"];
	}

	public function addWhereClauseForUser($user, &$query)
    {
		$debug = true;
		
        $flatRoleDataAccess = DataAccessManager::get("flat_roles");

		$isDev              = $flatRoleDataAccess->isUserInRoleNamed("DEV",             $user);
        $isAdminAdmin       = $flatRoleDataAccess->isUserInRoleNamed("SOFTWARE_ADMIN",  $user);
        $isAdminUser        = $flatRoleDataAccess->isUserInRoleNamed("ADMIN_USER",      $user);

		if ($isAdminAdmin || $isAdminUser || $isDev)
		{
			if ($debug)
			{
				// error_log("`addWhereClauseForUser` - isDev ($isDev) - isAdmimAdmin ($isAdminAdmin) - isAdminUser ($isAdminUser)");
				error_log("`addWhereClauseForUser` - isDev - isAdmimAdmin - isAdminUser");

			}
			return; // Allow All
		}

		$roles = $flatRoleDataAccess->rolesUserCanAddTo($user);

		if ($debug)
		{
			error_log("PersonaDataAccess/addWhereClauseForUser - `rolesUserCanAddTo`".print_r($roles, true));
		}

		$hasRoles = $roles && (count($roles) > 0);

		if (!$hasRoles)
		{
			throw new Exception("Not allowed - user has no roles they can add to.");
		}

		$roleRelations = $flatRoleDataAccess->roleRelationsModifiableByUser($user);

		if ($debug)
		{
			error_log("`PersonaDataAccess/addWhereClauseForUser - Role Relations Modifiable By User: ".print_r($roleRelations, true));
		}

		$userIDS = [];

		foreach ($roleRelations as $roleRelation)
		{
			$userIDS = array_merge($userIDS, $flatRoleDataAccess->userIDSForRoleRelationsModifiableByRoleRelation($roleRelation));
		}

		if ($debug)
		{
			error_log("Got User IDS: ".print_r($userIDS, true));
		}

		$whereGroup = new WhereGroup("OR");

		$whereGroup->addWhereClause(new WhereClause(
			"id", "IN", $userIDS
		));
		$whereGroup->addWhereClause(new WhereClause(
			"created_by_user_id", "=", $this->valueForKey("id", $user)
		));

		$query->addWhereClause($whereGroup);
	}

	


	public static function getCurrentUser()
	{
		return DataAccessManager::get("session")->getCurrentApacheUserOrSendToLoginWithRedirect("/auth/login.php");
	}

	public function getUserFromToken($token)
	{
		$debug = false;

		$id = htmlspecialchars_decode(explode('_', $token)[0]);
		
		if ($debug) 
		{ 
			error_log("Searching for current user with Cedula: ".$id); 
		}
		
		$current_user = DataAccessManager::get('persona')->getByIdentifier($id);

		if (DataAccessManager::get("persona")->isActive($current_user))
		{
			return null;
		}

		return $current_user;
	}

	public static function hydrateUserWithRoles(&$user)
	{
		$roles = DataAccessManager::get('role_person_relationships')->rolesForUser($user);
		$user["roles"] = $roles;
	}

	public static function deleteCurrentSessionCookie()
	{
		unset($_COOKIE['AuthCookie']);
		setcookie('AuthCookie', '', -1, '/'); 
	}

	public static function logout($returnToPath = null)
	{
		die(Glang::get("session_expired_message"));
	}

	public function fullNameForItem($item)
	{
		return $this->valueForKey("nombres", $item)." ".$this->valueForKey("apellidos", $item);
	}
	
	public function register()
	{	
		$debug = 0;
	
		if ($debug)
		{
			error_log('User Data Access Register (getDB):');
		}
		
		$db = $this->getDB();

		$passwordVirtualColumn = new GTKColumnVirtual($this, "password", [
            'formLabel'        => Glang::get("password"),
			'type'             => 'password', 
			'assignTo'		   => 'password_hash', 
			"process" 		   => function($value) { return password_hash($value, PASSWORD_DEFAULT); }, 
			"allowedFormTypes" => ["new"],
			'removeOnForms'	   => true,
			'hideOnShow'	   => true,
			'hideOnForms'	   => true, 
			'hideOnLists' 	   => true,
			'isInvalid' 		   => function($value) { 
				$value = trim($value);

				if (strlen($value) < 12)
				{
					return new FormResult(0, Glang::get("password_too_short"));
				}

				    // Check for symbol and number
				if (!preg_match('/[!@#$%^&*(),.?":{}|<>0-9]/', $value)) {
					return new FormResult(0, Glang::get("password_needs_character_and_symbols"));
				}
			},
		]);

		
		$checkCedulaForProblems = function($value) { 
			if (!verifyCedula($value)) 
			{ 
				return new FormResult(0, Glang::get("invalid_goverment_id")); 
			}
		};
	
		$columnMappings = [
			new GTKColumnMapping($this, "id", [
				"isPrimaryKey"    => true,
				"isAutoIncrement" => true,
			]),
			new GTKColumnMapping($this, "created_by_user_id", [
				"hideOnForms" => true,
				"valueWhenNewForUser" => function ($user, $item){
					return $user["id"];
				},
			]),
			new GTKColumnMapping($this, "cedula", [
				"isUnique"     => true, 
				'isInvalid'    => $checkCedulaForProblems, 
				"processOnAll"  => function ($rawEmail) { return strtolower($rawEmail); },
			]),
			new GTKColumnMapping($this, "nombres"),
			new GTKColumnMapping($this, "apellidos"),
			new GTKColumnMapping($this, "email", [
				"isRequired"    => true,
                "isUnique"      => true,
				"processOnAll"  => function ($rawEmail) { return strtolower($rawEmail); },
			]),
			$passwordVirtualColumn,
			new GTKColumnMapping($this, "password_hash", [
				'hideOnShow'  => true,
                'hideOnForms' => true, 
                'hideOnLists' => true
            ]),
			new GTKColumnMapping($this, "fecha_creado"),
			new GTKColumnMapping($this, "fecha_modificado"),
			new GTKColumnMapping($this, "estado", [
				'formInputType' => 'select', 
				'possibleValues' => [ 
					'activo' 	 => ['label'=>'Activo'], 
					'inactivo' 	 => ['label'=>'Inactivo'],
				],
			]),
		];

		$this->dataMapping		    = new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumn = "fecha_creado";
		$this->defaultOrderByOrder  = "DESC";	
		
		///
		///
		///
		
		$resetPasswordAction = new DataAccessAction($this, "resetPassword", "Send Reset Password Link");
		$resetPasswordAction->allowedFor = [
			"SOFTWARE_ADMIN",
			"DEVS",
		];
		$resetPasswordAction->doObjectForUserItemDelegateOptions   = DataAccessManager::get("RequestPasswordResetController");
		$resetPasswordAction->doFunctionForUserItemDelegateOptions = "sendResetPasswordLinkFromAdminFromUserToUserDelegateOptions";

		$this->addAction($resetPasswordAction);

		///
		///
		///

		$activateDeactivateUser = new DataAccessAction($this, "activateDeactivateUser", "Send Reset Password Link");
		$activateDeactivateUser->label = function ($user, $item) {
			if (DataAccessManager::get("persona")->isActive($item))
			{
				return "Desactivar";
			}
			else
			{
				return "Activar";
			}
		};
		$activateDeactivateUser->isInvalidForUserItemDelegateOptionsFunction = function ($user, $item, $delegate, $options) {

			/*
			
			"qualifier_model"        => "agencia", 
			"qualifier_model_column" => "id",
			"qualifier_item_column"  => "agencia_id",
			"qualifier_value"        => $agenciaID, 

			function hasPersmission($permission, $user, $item)
			{
				$userRoles = $roleDataAccess->rolesForUser($user);

				foreach ($userRoles as $roles)
				{
					$permission = $rolePermissionRelationships->hasPermission($permission, $role);

					if (!$permission)
					{
						continue;
					}

					$qualifier = $rolePermissionRelationships->getQualifier($permission);

					if (!$qualifier)
					{
						return true;
					}

					$qualifier

					$itemMeetsQualifier...

					$item = DataAccessManager::get(")
				}

				return false;
			}

			*/
			// return DataAccessManager::get("permission")->hasPermission("toggle_user_active", $user, $userToBeActivated);

			$debug = true;

			if (DataAccessManager::get("persona")->isInGroups($user, [
			   "SOFTWARE_ADMIN",
			   "DEV",
			])){
				return false;
			}

			if ($debug)
			{
				error_log("Not an ADMIM or a dev");
			}
			
			$isUserInAgency     = DataAccessManager::get("flat_roles")->isUserInRoleNamed("AGENCY", $user);
			$isUserAdminForRole = DataAccessManager::get("flat_roles")->valueForKey("is_admin_for_role", $isUserInAgency);

			if ($isUserInAgency && $isUserAdminForRole)
			{
				$isItemInAgency = DataAccessManager::get("flat_roles")->isUserInRoleNamed("AGENCY", $item);
				
				if ($isItemInAgency)
				{
					$userQualifier = DataAccessManager::get("flat_roles")->valueForKey("qualifier", $isUserInAgency);
					$itemQualifier = DataAccessManager::get("flat_roles")->valueForKey("qualifier", $isItemInAgency);

					if ($userQualifier == $itemQualifier)
					{
						return false;
					}
				}
			}


			return true;
		};
		$activateDeactivateUser->doObjectForUserItemDelegateOptions   = $this;
		$activateDeactivateUser->doFunctionForUserItemDelegateOptions = "toggleUserActiveByUserAndUserItemDelegateOptions";

		$this->addAction($activateDeactivateUser);
	}

	public function toggleUserActiveByUserAndUserItemDelegateOptions($user, $item, $delegate, $options)
	{
		$toPublish = "";

		try
		{
			$userWasActive = $this->isActive($item);

			$this->toggleUserActiveByUserAndUserItem($user, $item);

			$toPublish = "";

			if ($userWasActive)
			{
				$toPublish .= "Usuario fue desactivado existosamente.";
			}
			else
			{
				$toPublish .= "Usuario fue desactivado existosamente.";
			}
		}
		catch (Exception $e)
		{
			$toPublish .= "Hubo un problema ejecutando esta acción. Intente nuevamente o reporte al administrador.";
		}

		$toPublish .= "<br/>";
		$toPublish .=  AllLinkTo("persona", ["label" => "Volver a lista", ]);
		$toPublish .= "<br/>";
		$toPublish .= '<a href="/">Ir a inicio</a>';

		die($toPublish);
	}

	public function toggleUserActiveByUserAndUserItem($user, $item)
	{
		if ($this->isActive($item))
		{
			$item["estado"] = "inactivo";
		}
		else
		{
			$item["estado"] = "activo";
		}

		$this->update($item);
	}

	public function isActive($user)
	{
		$debug=1;
		if (!$user)
		{
			return false;
		}

		$activeValue = $this->valueForKey("estado", $user);
		if ($debug)
			{
				error_log("este usuario esta: ".$activeValue);
			}
		if ($activeValue !== "activo")
		{
			if($debug)
			{
				error_log("false");
			}
			return false;
		}
		if($debug)
		{
			error_log("true");
		}
		return true;

	}

	public function updatePasswordHashForPersona($persona, $newPassword)
	{
		$debug = false;

		$toUpdate = [];

		if ($debug)
		{
			error_log("Will update password for user: ".print_r($persona, true));
		}

		$toUpdate["id"] = $this->valueForIdentifier($persona);
		$toUpdate["password_hash"] = password_hash($newPassword, PASSWORD_DEFAULT);

		if ($debug)
		{
            error_log("Will update password hash. Using object: ".print_r($toUpdate, true));
		}

		$this->update($toUpdate);
	}

	
	public function findUserByCedula($cedula) 
	{
		$debug = 0;

		$query = "SELECT * FROM {$this->tableName()} WHERE cedula = :cedula";
		
		$db = $this->getDB();
		
		$statement = $db->prepare($query);

		$statement->bindValue(':cedula', sanitizeCedula($cedula));
				
		// Execute the query
		$statement->execute();
			
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		
		if ($debug)
		{
			error_log("`findUserByCedula` result: ".serialize($row));
		}
		
		return $row;
	}	

	// public function createUserWithNoPassword($)

	public function createUserIfNotExists($user)
	{
		if (!$this->where("email", $user['email']))
		{
			if ($user["password"])
			{
				$password_hash = password_hash($user['password'], PASSWORD_DEFAULT);
				$user['password_hash'] = $password_hash;
			}

			$this->createUser($user);		
		}
	}
	
	public function createUser($user)
	{
		$query = "INSERT INTO {$this->tableName()} 
			(cedula,  nombres,  apellidos,  email,  password_hash, fecha_creado, estado)
			VALUES
			(:cedula, :nombres, :apellidos, :email, :password_hash, :fecha_creado, 'activo')";
			
		$statement = $this->getDB()->prepare($query);

		$cedula = null;

		if (isset($user["cedula"]))
		{
			$cedula = sanitizeCedula($user["cedula"]);
		}

		$statement->bindValue(':cedula',    	$cedula);
		$statement->bindValue(':nombres',   	$user["nombres"]);
		$statement->bindValue(':apellidos', 	$user["apellidos"]);
		$statement->bindValue(':email', 		$user["email"]);
		$statement->bindValue(':password_hash', $user["password_hash"]);
		$statement->bindValue(':fecha_creado',  date(DATE_ATOM) );
		
		// Execute the INSERT statement
		$result = $statement->execute();
		
		if ($result) 
		{
			return $this->getDB()->lastInsertId();
		} 
		else 
		{
			error_log('INSERT FAILED');
			return 0;
		}
	}


	public function isInGroup(&$user, $group)
	{
		if (is_array($group))
		{
			return $this->isInGroups($user, $group);
		}
		else if (is_string($group))
		{
			return $this->isInGroups($user, [ $group ]);
		}
		
	}

	public function isInGroups(&$user, $groups)
	{
		$debug = false;

		if (!$user)
		{
			return false;
		}

		if ($debug)
		{
			error_log("`userIsInGroup` - ".print_r($user, true));
		}

		$roleRelations = null;

		if (!isset($user["flat_roles"]))
		{
			if ($debug)
			{
				error_log("Searching for roles...");
			}
			$user["flat_roles"] = DataAccessManager::get("flat_roles")->where("user_id", $this->valueForKey("id", $user));
		}

		$roleRelations = $user["flat_roles"];

		if ($debug)
		{
			error_log("Got roles...: ".print_r($roles, true));
		}

		$userRoles = null;

		if (!isset($user["roles"]))
		{
			$roleIDS = [];

			foreach ($roleRelations as $roleRelation)
			{
				$roleIDS[] = $roleRelation["role_id"];
			}

			$query = new SelectQuery(DataAccessManager::get("roles"));

			$query->addClause(new WhereClause(
				"id", "IN", $roleIDS
			));

			$user["roles"] = $query->executeAndReturnAll();
		}

		$userRoles = $user["roles"]; 

		$userRoleNames = [];

		if (!isset($user["userRoleNames"]))
		{
			foreach ($userRoles as $role)
			{
				$userRoleNames[] = $role["name"];
			}
			$user["userRoleNames"] = $userRoleNames;
		}
		$userRoleNames = $user["userRoleNames"];

		if ($debug)
		{
			error_log("Got user role names: ".print_r($userRoleNames, true));
		}

		$intersection = array_intersect($groups, $userRoleNames);

		if ($debug)
		{
			error_log("Intersection (".count($intersection).") - ".print_r($intersection, true));
		}

		return (count($intersection) > 0);
	}

	public function isDeveloper($user = null) 
	{
		return $this->isInGroup($user, "DEV");
	}
	public function isAdmin($user = null) 
	{	
		return $this->isInGroup($user, "SOFTWARE_ADMIN");
	}

	public function assignRoleToUser($role, $user)
	{
		$this->assignRoleToUserByUserDetails($role, $user, null, null);
	}

	public function assignRoleToUserByUserDetails($maybeRole, $user, $grantingUser = null, $grantingDetails = null)
	{	
		$debug = true;

		$role = null;

		if (is_array($maybeRole))
		{
			$role = $maybeRole;
		}
		else
		{
			$role = DataAccessManager::get("roles")->getOne("name", $maybeRole);
		}

		if (!$role)
		{
			throw new Exception("Role not found `{$maybeRole}`");
		}

		if ($debug)
		{
			error_log("Role: ".print_r($role, true));
		}

		$roleID = $this->valueForKey("id", $role);
		$userID = $this->valueForKey("id", $user);

		// $rolePersonRelationships = DataAccessManager::get("role_person_relationships");
		// $rolePersonRelationships = DataAccessManager::get("flat_roles");

		if (DataAccessManager::get("roles")->isQualifiedRole($role))
		{
			if ($debug)
			{
				error_log("Qualified role");
			}

			if (!$grantingUser)
			{
				throw new Exception("A qualified role must be granted by a specific user");
			}

			$grantingUserID = $this->valueForKey("id", $grantingUser);
		}
		else
		{
			if ($debug)
			{
				error_log("Not a qualified role");
			}

			$toInsert = [
				"role_id" => $roleID,
				"user_id" => $userID,
			];
			
			DataAccessManager::get("flat_roles")->insert($toInsert);
		}
	}
}


/*

	public static function getCurrentSessionAndAllowRedirectBack($redirectBack = false)
	{
		static $didLookForSession = false;
		static $isAuthenticated   = null;
		static $currentSession    = null;

		if (!$didLookForSession)
		{
			$debug = false;

			$authToken = $_COOKIE['AuthCookie'];
	
			if ($debug) 
			{ 
				error_log("Searching for current user with `authToken`: ".$authToken); 
			}
		
			$currentSession = DataAccessManager::get("session")->getSessionById($authToken);

			if ($currentSession)
			{
				$isAuthenticated = DataAccessManager::get("session")->verifySession($currentSession);

				if (!$isAuthenticated)
				{
					self::logout();
				}
			}

			$didLookForSession = true;
		}

		return $currentSession;
	}


	public static function isAuthenticatedSession()
	{
		$authToken = $_COOKIE['AuthCookie'];

		return self::isAuthenticatedSession($authToken);
	}

	public function isAuthenticatedToken($authToken)
	{
		$debug = false;
	
		if ($debug) 
		{ 
			error_log("Searching for current user with `authToken`: ".$authToken); 
		}
	
		$currentSession = DataAccessManager::get("session")->getSessionById($authToken);

		if ($currentSession)
		{
			$isAuthenticated = DataAccessManager::get("session")->verifySession($currentSession);

			if (!$isAuthenticated)
			{
				self::logout();
			}
		}
	}

*/
