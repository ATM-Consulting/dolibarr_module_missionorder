<?php

class TMissionOrder extends TObjetStd
{
	/**
	 * Draft status
	 */
	const STATUS_DRAFT = 0;
	/**
	 * Validated status
	 */
	const STATUS_VALIDATED = 1;
	/**
	 * Refused status
	 */
	const STATUS_REFUSED = 2;
	/**
	 * Accepted status
	 */
	const STATUS_ACCEPTED = 3;
	
	public function __construct()
	{
		global $langs;
		
		$this->set_table(MAIN_DB_PREFIX.'mission_order');
		
		$this->add_champs('ref', array('type' => 'string', 'length' => 80, 'index' => true));
		$this->add_champs('label,location,other_reason,other_carriage', array('type' => 'string'));
		$this->add_champs('status', array('type' => 'integer'));
		
		$this->add_champs('fk_project,entity,fk_user_author,fk_user_valid', array('type' => 'integer', 'index' => true));
		$this->add_champs('date_start,date_end,date_valid,date_refuse,date_accept', array('type' => 'date'));
		$this->add_champs('note', array('type' => 'text'));
		
		$this->_init_vars();
		$this->start();
		
		$this->setChild('TMissionOrderUser','fk_mission_order');
		$this->setChild('TMissionOrderReason','fk_mission_order');
		$this->setChild('TMissionOrderCarriage','fk_mission_order');
		
		$this->date_start = null;
		$this->date_end = null;
		
		$this->errors = array();
	}
	
	public function save(&$PDOdb, $addprov=false)
	{
		$res = parent::save($PDOdb);
		
		if ($addprov)
		{
			$this->ref = '(PROV'.$this->getId().')';
			
			$wc = $this->withChild;
			$this->withChild = false;
			$res = parent::save($PDOdb);
			$this->withChild = $wc;
		}
		
		return $res;
	}
	
	public function setValid(&$PDOdb, &$user)
	{
		$this->ref = $this->getNumero();
		$this->date_valid = dol_now();
		$this->status = self::STATUS_VALIDATED;
		$this->fk_user_valid = $user->id;
		
		// TODO envoyer mail aux valideurs
		
		return parent::save($PDOdb);
	}
	
	public function setDraft(&$PDOdb)
	{
		$this->status = self::STATUS_DRAFT;
		$this->withChild = false;
		
		return parent::save($PDOdb);
	}
	
	public function getNumero()
	{
		if (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref))
		{
			return $this->getNextNumero();
		}
		
		return $this->ref;
	}
	
	private function getNextNumero()
	{
		global $db,$conf;
		
		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		
		$mask = !empty($conf->global->MISSION_ORDER_REF_MASK) ? $conf->global->MISSION_ORDER_REF_MASK : 'OM{00000}';
		$numero = get_next_value($db, $mask, 'mission_order', 'ref');
				
		return $numero;
	}
	
	public function setChildren(&$PDOdb, &$TabId, $child_name, $fk_foreign_key, $attr_fk_object)
	{
		foreach ($this->{$child_name} as &$child)
		{
			$child->to_delete = true;
			$child_id = $child->getId();
			
			if (!empty($TabId[$child_id]))
			{
				$child->to_delete = false; // Finalement il est présent dans le tableau de valeur, du coup je le delete pas
				unset($TabId[$child_id]); // unset car déjà existant comme enfant, donc pas besoin de le add via le traitement suivant
			}
		}
		
		if (!empty($TabId))
		{
			foreach ($TabId as $fk_object)
			{
				$k = $this->addChild($PDOdb, $child_name);
				$this->{$child_name}[$k]->{$attr_fk_object} = $fk_object;
			}
		}
	}

	public function setUsers(&$PDOdb, &$TUserId)
	{
		$this->setChildren($PDOdb, $TUserId, 'TMissionOrderUser', 'fk_mission_order', 'fk_user');
	}
	
	public function setReasons(&$PDOdb, &$TReasonId)
	{
		$this->setChildren($PDOdb, $TReasonId, 'TMissionOrderReason', 'fk_mission_order', 'fk_c_mission_order_reason');
	}
	
	public function setCarriages(&$PDOdb, &$TCarriageId)
	{
		$this->setChildren($PDOdb, $TCarriageId, 'TMissionOrderCarriage', 'fk_mission_order', 'fk_c_mission_order_carriage');
	}

	
	public function getNomUrl($withpicto=0, $get_params='')
	{
		global $langs;

        $result='';
        $label = '<u>' . $langs->trans("ShowMissionOrder") . '</u>';
        if (! empty($this->ref)) $label.= '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
        
        $linkclose = '" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $link = '<a href="'.dol_buildpath('/missionorder/card.php', 1).'?id='.$this->getId(). $get_params .$linkclose;
       
        $linkend='</a>';

        $picto='generic';
		
        if ($withpicto)
            $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2)
            $result.=' ';
        $result.=$link.$this->ref.$linkend;
		
        return $result;
	}
	
	public static function getStaticNomUrl($id, $withpicto=0)
	{
		global $PDOdb;
		
		if (empty($PDOdb)) $PDOdb = new TPDOdb;
		
		$object = new TMissionOrder;
		$object->load($PDOdb, $id, false);
		
		return $object->getNomUrl($withpicto);
	}
	
	public function getLibStatut($mode=0)
    {
        return $this->LibStatut($this->status, $mode);
    }
	
	public function LibStatut($status, $mode)
	{
		global $langs;
		$langs->load("missionorder@missionorder");

		if ($status==self::STATUS_DRAFT) { $statustrans='statut0'; $keytrans='MissionOrderStatusDraft'; }
		if ($status==self::STATUS_VALIDATED) { $statustrans='statut1'; $keytrans='MissionOrderStatusValidated'; }
		if ($status==self::STATUS_REFUSED) { $statustrans='statut5'; $keytrans='MissionOrderStatusRefused'; }
		if ($status==self::STATUS_ACCEPTED) { $statustrans='statut6'; $keytrans='MissionOrderStatusAccepted'; }

		
		if ($mode == 0) return img_picto($langs->trans('title'), $statustrans);
		else return img_picto($langs->trans('title'), $statustrans).' '.$langs->trans($keytrans);
	}
}

class TMissionOrderUser extends TObjetStd
{
	public function __construct()
	{
		$this->set_table(MAIN_DB_PREFIX.'mission_order_user');
		
		$this->add_champs('fk_mission_order,fk_user', array('type' => 'integer', 'index' => true));
		
		$this->_init_vars();
		$this->start();
		
		$this->user = null;
	}
	
	public function load(&$PDOdb, $id)
	{
		$res = parent::load($PDOdb, $id);
		$this->loadUser($PDOdb);
		
		return $res;
	}
	
	public function loadBy(&$PDOdb, $value, $field, $annexe = false)
	{
		$res = parent::loadBy($PDOdb, $value, $field, $annexe);
		$this->loadUser($PDOdb);
		
		return $res;
	}
	
	public function loadUser($PDOdb)
	{
		global $db,$langs;
		
		if (!class_exists('User')) require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		
		$this->user = new User($db);
		$this->user->fetch($this->fk_user);
	}
}

class TMissionOrderReason extends TObjetStd
{
	public function __construct()
	{
		$this->set_table(MAIN_DB_PREFIX.'mission_order_reason');
		
		$this->add_champs('fk_mission_order,fk_c_mission_order_reason', array('type' => 'integer', 'index' => true));
		
		$this->_init_vars();
		$this->start();
		
		$this->label = null;
		$this->entity = null;
		$this->active = null;
	}
	
	public function load(&$PDOdb, $id)
	{
		$res = parent::load($PDOdb, $id);
		$this->loadDictionnaireInfo($PDOdb);
		
		return $res;
	}
	
	public function loadBy(&$PDOdb, $value, $field, $annexe = false)
	{
		$res = parent::loadBy($PDOdb, $value, $field, $annexe);
		$this->loadDictionnaireInfo($PDOdb);
		
		return $res;
	}
	
	public function loadDictionnaireInfo($PDOdb)
	{
		$TRow = $PDOdb->ExecuteAsArray('SELECT label, entity, active FROM '.MAIN_DB_PREFIX.'c_mission_order_reason WHERE rowid = '.$PDOdb->quote($this->fk_c_mission_order_reason));
		if (!empty($TRow))
		{
			$this->label = $TRow[0]->label;
			$this->entity = $TRow[0]->entity;
			$this->active = $TRow[0]->active;
		}
		
		if ($this->label === null)
		{
			global $langs;
			
			$this->label = $langs->trans('error_rowid_in_dict_not_found', $this->fk_c_mission_order_reason);
			$this->entity = -1;
			$this->active = -1;
		}
	}
}

class TMissionOrderCarriage extends TObjetStd
{
	public function __construct()
	{
		$this->set_table(MAIN_DB_PREFIX.'mission_order_carriage');
		
		$this->add_champs('fk_mission_order,fk_c_mission_order_carriage', array('type' => 'integer', 'index' => true));
		
		$this->_init_vars();
		$this->start();
		
		$this->label = null;
		$this->active = null;
	}
	
	public function load(&$PDOdb, $id)
	{
		$res = parent::load($PDOdb, $id);
		$this->loadDictionnaireInfo($PDOdb);
		
		return $res;
	}
	
	public function loadBy(&$PDOdb, $value, $field, $annexe = false)
	{
		$res = parent::loadBy($PDOdb, $value, $field, $annexe);
		$this->loadDictionnaireInfo($PDOdb);
		
		return $res;
	}
	
	public function loadDictionnaireInfo($PDOdb)
	{
		$TRow = $PDOdb->ExecuteAsArray('SELECT label, entity, active FROM '.MAIN_DB_PREFIX.'c_mission_order_carriage WHERE rowid = '.$PDOdb->quote($this->fk_c_mission_order_carriage));
		if (!empty($TRow))
		{
			$this->label = $TRow[0]->label;
			$this->entity = $TRow[0]->entity;
			$this->active = $TRow[0]->active;
		}
		
		if ($this->label === null)
		{
			global $langs;
			
			$this->label = $langs->trans('error_rowid_in_dict_not_found', $this->fk_c_mission_order_carriage);
			$this->entity = -1;
			$this->active = -1;
		}
	}
}