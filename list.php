<?php

require 'config.php';
dol_include_once('/missionorder/class/missionorder.class.php');
if (!empty($conf->valideur->enabled) && !empty($user->rights->missionorder->all->approve)) dol_include_once ('/valideur/class/valideur.class.php');

if(empty($user->rights->missionorder->read)) accessforbidden();

$langs->load('missionorder@missionorder');
$langs->load('abricot@abricot');

$hookmanager->initHooks(array('missionorderlist'));

$PDOdbGlobal = new TPDOdb;
$line_approve_counter = 0;

/*
 * Actions
 */

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	if (GETPOST('approve_all_checked') && !empty($conf->valideur->enabled) && !empty($user->rights->missionorder->all->approve))
	{
		
		$TMissionOrderId = GETPOST('TMissionOrderId', 'array');
		foreach ($TMissionOrderId as $id)
		{
			if (empty($id)) continue;
			
			$missionorder = new TMissionOrder;
			$missionorder->load($PDOdbGlobal, $id);

			if ($missionorder->status != TMissionOrder::STATUS_TO_APPROVE) continue;

			$TUsersGroup = $missionorder->getUsersGroup(1);
			if (TRH_valideur_groupe::canBeValidateByThisUser($PDOdbGlobal, $user, $missionorder, $TUsersGroup, 'missionOrder', $missionorder->entity))
			{
				$missionorder->addApprobation($PDOdbGlobal);
			}
		}
		
		$get = urldecode($_SERVER['QUERY_STRING']);
		$get = preg_replace('/&?TMissionOrderId\[\]=\d*|&?approve\_all\_checked=[a-zA-Z\ ]*/', '', $get);
		
		header('Location: '.$_SERVER['PHP_SELF'].'?'.$get);
		exit;
	}
}


/*
 * View
 */

_list();

function _list()
{
	global $db,$langs,$user,$conf,$hookmanager, $line_approve_counter;
	
	llxHeader('',$langs->trans('listMissionOrder'),'','');
	
	$type = GETPOST('type');
	if (empty($user->rights->missionorder->all->read)) $type = 'mine';

	// TODO ajouter les colonnes manquantes ET une colonne action pour la notion de validation rapide
	$sql = 'SELECT mo.rowid, mo.ref, mo.label, mo.location, mo.fk_project, mo.date_start, mo.date_end, mo.date_refuse, mo.date_accept
					, mo.status, GROUP_CONCAT(mou.fk_user SEPARATOR \',\') as TUserId';
	
	// TODO il faut aussi check si l'user à le droit d'accepter des OM
	if (!empty($user->rights->missionorder->all->approve)) $sql .= ', \'\' as action';
	
	$sql.= '
			FROM '.MAIN_DB_PREFIX.'mission_order mo
			LEFT JOIN '.MAIN_DB_PREFIX.'projet p ON (p.rowid = mo.fk_project)
			LEFT JOIN '.MAIN_DB_PREFIX.'mission_order_user mou ON (mou.fk_mission_order = mo.rowid)
			WHERE mo.entity IN ('.getEntity('TMissionOrder', 1).')';
	
	if ($type == 'to_approve') $sql.= 'AND mo.status = '.TMissionOrder::STATUS_TO_APPROVE;
	
	
	
	if ($type == 'mine') $sql.= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'mission_order_user mou2 WHERE mou2.fk_mission_order=mo.rowid AND mou2.fk_user = '.$user->id.' )';
	// équivalent avec un "IN"
	// if ($type == 'mine') $sql.= ' AND mo.rowid IN (SELECT mo2.rowid FROM '.MAIN_DB_PREFIX.'mission_order mo2 INNER JOIN '.MAIN_DB_PREFIX.'mission_order_user mou2 ON (mo2.rowid = mou2.fk_mission_order) WHERE mou2.fk_user =  '.$user->id.')';
	
	if ($type == 'to_approve') 
	{
		if (!empty($conf->global->VALIDEUR_HIERARCHIE_ENABLED))
		{
			// Reste à check si l'OM n'a pas déjà était apprové
			// NOT EXISTS => mo.rowid NOT IN rh_valideur_object rhvo pour le user courant
			// EXISTS => que l'OM référence un users du même groupe que le user courant (valideur courant)
			// rhvg.level = au prochain level qui doit valider (si 1 et 2 on validé ceci retourne 3; si personne n'a validé alors sa retour 1; si 1 et 3 ont validés sa retourne 2 [cas particulier qui n'est pas sensé ce produire sauf si la conf hiérarchique a sauté passé un temps])
			$sql.= '
				AND EXISTS (
					SELECT 1 FROM '.MAIN_DB_PREFIX.'usergroup_user ugu 
					WHERE ugu.fk_user = mou.fk_user
					AND EXISTS (
						SELECT 1 FROM '.MAIN_DB_PREFIX.'rh_valideur_groupe rhvg 
						WHERE rhvg.fk_usergroup = ugu.fk_usergroup
						AND rhvg.fk_user = '.$user->id.'
						AND rhvg.type = \'missionOrder\'
						AND rhvg.entity = mo.entity

						AND rhvg.level = (
							SELECT COALESCE(MAX(vg2.level)+1, 1) FROM '.MAIN_DB_PREFIX.'rh_valideur_groupe vg2 
							INNER JOIN '.MAIN_DB_PREFIX.'rh_valideur_object vo2 ON (vo2.fk_user = vg2.fk_user )
							WHERE vg2.type = \'missionOrder\'

							AND vo2.type = \'missionOrder\'
							AND vo2.fk_object = mo.rowid
						)
					)
				)		
			';
		}
	}
	
	$sql.= ' GROUP BY mo.rowid';
	
	$PDOdb = new TPDOdb;
	$missionorder = new TMissionOrder;
	
	$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_mission_order', 'GET');
	
	$nbLine = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : $conf->global->MAIN_SIZE_LISTE_LIMIT;
	
	$r = new TSSRenderControler($missionorder);
	$r->liste($PDOdb, $sql, array(
		'limit'=>array(
			'nbLine' => $nbLine
		)
		,'subQuery' => array()
		,'link' => array()
		,'type' => array(
			'date_start' => 'date'
			,'date_end' => 'date'
			,'date_refuse' => 'date'
			,'date_accept' => 'date'
		)
		,'search' => array(
			'ref' => array('recherche' => true)
			,'label' => array('recherche' => true)
			,'location' => array('recherche' => true)
			,'fk_project' => array('recherche' => true, 'table' => array('p', 'p'), 'field' => array('ref','title'))
			,'date_start' => array('recherche' => 'calendars', 'allow_is_null' => true)
			,'date_end' => array('recherche' => 'calendars', 'allow_is_null' => true)
			,'date_refuse' => array('recherche' => 'calendars', 'allow_is_null' => true)
			,'date_accept' => array('recherche' => 'calendars', 'allow_is_null' => true)
			,'status' => array('recherche' => TMissionOrder::$TStatus, 'to_translate' => true)
		)
		,'translate' => array()
		,'hide' => array(
			'rowid'
		)
		,'liste' => array(
			'titre' => $langs->trans('ListMissionOrder')
			,'image' => img_picto('','title_generic.png', '', 0)
			,'picto_precedent' => '<'
			,'picto_suivant' => '>'
			,'noheader' => 0
			,'messageNothing' => $langs->trans('NoMissionOrder')
			,'picto_search' => img_picto('','search.png', '', 0)
		)
		,'title'=>array(
			'ref' => $langs->trans('Ref')
			,'label' => $langs->trans('Label')
			,'location' => $langs->trans('Location')
			,'fk_project' => $langs->trans('Project')
			,'date_start' => $langs->trans('DateStart')
			,'date_end' => $langs->trans('DateEnd')
			,'date_refuse' => $langs->trans('DateRefused')
			,'date_accept' => $langs->trans('DateAccepted')
			,'status' => $langs->trans('Status')
			,'TUserId' => $langs->trans('UsersLinked')
			,'action' => $langs->trans('Action')
		)
		,'eval'=>array(
			'ref' => 'TMissionOrder::getStaticNomUrl(@rowid@, 1)'
			,'fk_project' => '_getProjectNomUrl(@val@)'
			,'date_start' => '_formatDate("@val@")'
			,'date_end' => '_formatDate("@val@")'
			,'date_refuse' => '_formatDate("@val@")'
			,'date_accept' => '_formatDate("@val@")'
			,'status' => 'TMissionOrder::LibStatut(@val@, 4)'
			,'TUserId' => '_getUsersLink("@val@")'
			,'action' => '_isValideur("@rowid@")'
		)
	));
	
	$parameters=array('sql'=>$sql);
	$reshook=$hookmanager->executeHooks('printFieldListFooter', $parameters, $missionorder);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	
	
	if (!empty($conf->valideur->enabled) && !empty($user->rights->missionorder->all->approve))
	{
		echo '<div class="tabsAction">
			<div class="inline-block divButAction">
				<input name="approve_all_checked" type="submit" value="'.$langs->trans('ApproveAllChecked').'" class="button">
			</div>
		</div>';
	}
	
	$formcore->end_form();
	
	llxFooter('');
}

function _getProjectNomUrl($fk_project)
{
	global $db;
	
	$project = new Project($db);
	$project->fetch($fk_project);
	
	return $project->getNomUrl(1, '', 1);
}

function _formatDate($date)
{
	global $db;
	
	if (empty($date)) return '';
	
	// Besoin du $db->jdate pour éviter un décalage de 1 heure
	return dol_print_date($db->jdate($date), 'dayhour');
}

function _getUsersLink($fk_user_string)
{
	global $db,$TUserLink;
	
	if (empty($TUserLink)) $TUserLink = array();
	$Tab = explode(',', $fk_user_string);
	
	$res = '';
	foreach ($Tab as $fk_user)
	{
		if (!empty($TUserLink[$fk_user])) $u = &$TUserLink[$fk_user];
		else
		{
			$u = new User($db);
			$u->fetch($fk_user);
			$TUserLink[$fk_user] = $u;
		}
		
		if ($u->id > 0) $res .= $u->getNomUrl(1, '', 0, 0, 24, 1).'&nbsp;';
		else $res .= '[IdNotFound:'.$fk_user.']';
	}
	
	return $res;
}

function _isValideur($fk_mission_order)
{
	global $PDOdbGlobal,$user,$line_approve_counter,$conf;
	
	if (empty($conf->valideur->enabled) || empty($user->rights->missionorder->all->approve)) return '';
	
	$missionorder = new TMissionOrder;
	$missionorder->load($PDOdbGlobal, $fk_mission_order);
	
	if ($missionorder->status != TMissionOrder::STATUS_TO_APPROVE) return '';
	
	$TUsersGroup = $missionorder->getUsersGroup(1);
	if (TRH_valideur_groupe::canBeValidateByThisUser($PDOdbGlobal, $user, $missionorder, $TUsersGroup, 'missionOrder', $missionorder->entity))
	{
		$line_approve_counter++;
		return '<input type="checkbox" name="TMissionOrderId[]" value="'.$fk_mission_order.'" />';
	}
	else return '';
}