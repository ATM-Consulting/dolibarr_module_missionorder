<!-- Un début de <div> existe de par la fonction dol_fiche_head() -->
	<input type="hidden" name="action" value="[view.action]" />
	<table width="100%" class="border">
		<tbody>
			<tr class="ref">
				<td width="25%">[langs.transnoentities(Ref)]</td>
				<td>[view.showRef;strconv=no]</td>
			</tr>

			<tr class="label">
				<td width="25%">[langs.transnoentities(Label)]</td>
				<td>[view.showLabel;strconv=no]</td>
			</tr>

			<tr class="status">
				<td width="25%">[langs.transnoentities(Status)]</td>
				<td>[missionorder.getLibStatut(1);strconv=no]</td>
			</tr>

			<tr class="project">
				<td width="25%" >[langs.transnoentities(ProjectLinked)]</td>
				<td>[view.showProject;strconv=no]</td>
			</tr>

			<tr class="users">
				<td width="25%" class="fieldrequired">[langs.transnoentities(UsersLinked)]</td>
				<td>[view.showUsers;strconv=no]</td>
			</tr>
			
			<tr class="usergroup">
				<td width="25%" class="fieldrequired">[langs.transnoentities(UserGroupLinked)]</td>
				<td>[view.showUsergroup;strconv=no]</td>
			</tr>

			<tr class="location">
				<td width="25%">[langs.transnoentities(Location)]</td>
				<td>[view.showLocation;strconv=no]</td>
			</tr>

			<tr class="date_start">
				<td width="25%" class="fieldrequired">[langs.transnoentities(DateStart)]</td>
				<td>[view.showDateStart;strconv=no]</td>
			</tr>

			<tr class="date_end">
				<td width="25%" class="fieldrequired">[langs.transnoentities(DateEnd)]</td>
				<td>[view.showDateEnd;strconv=no]</td>
			</tr>

			<tr class="reason">
				<td width="25%">[langs.transnoentities(Motif de la mission)]</td>
				<td>[view.showReason;strconv=no]</td>
			</tr>

			<tr class="carriage">
				<td width="25%">[langs.transnoentities(Carriage)]</td>
				<td>[view.showCarriage;strconv=no]</td>
			</tr>

			<tr class="note">
				<td width="25%">[langs.transnoentities(Commentaire)]</td>
				<td>[view.showNote;strconv=no]</td>
			</tr>
			
			[onshow;block=begin;when [TNextValideur.#]+-0]
			<tr class="next_valideurs">
				<td width="25%">[langs.transnoentities(NextValideur)]</td>
				<td><p>[TNextValideur;block=p][TNextValideur.getNomUrl(1);strconv=no]</p></td>
			</tr>
			[onshow;block=end]
		</tbody>
	</table>

</div> <!-- Fin div de la fonction dol_fiche_head() -->

[onshow;block=begin;when [view.mode]='edit']
<div class="center">
	
	<!-- '+-' est l'équivalent d'un signe '>' (TBS oblige) -->
	[onshow;block=begin;when [missionorder.getId()]+-0]
	<input type='hidden' name='id' value='[missionorder.getId()]' />
	<input type="submit" value="[langs.transnoentities(Save)]" class="button" />
	[onshow;block=end]
	
	[onshow;block=begin;when [missionorder.getId()]=0]
	<input type="submit" value="[langs.transnoentities(CreateDraft)]" class="button" />
	[onshow;block=end]
	
	<input type="button" onclick="javascript:history.go(-1)" value="[langs.transnoentities(Cancel)]" class="button">
</div>
[onshow;block=end]

[onshow;block=begin;when [view.mode]!='edit']
<div class="tabsAction">
	[onshow;block=begin;when [user.rights.missionorder.write;noerr]=1]
	
		[onshow;block=begin;when [missionorder.status]=[TMissionOrder.STATUS_DRAFT]]
			[onshow;block=begin;when [view.allowed_user]=1]
			<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=validate" class="butAction">[onload.conf.global.MISSION_ORDER_VALIDATE_ACTION_FOR_APPROVAL;if [val]=1;then [langs.transnoentities(ValidateAndSendToBeApprove)];else [langs.transnoentities(Validate)];noerr]</a></div>
			<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=edit" class="butAction">[langs.transnoentities(Modify)]</a></div>
			[onshow;block=end]
		[onshow;block=end]
		
		[onshow;block=begin;when [missionorder.status]!=[TMissionOrder.STATUS_DRAFT]]
                        [onshow;block=begin;when [view.is_valideur]=1]
                           <div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=edit" class="butAction">[langs.transnoentities(Modify)]</a></div>
                        [onshow;block=end]
                [onshow;block=end]


		[onshow;block=begin;when [missionorder.status]=[TMissionOrder.STATUS_VALIDATED]]
			[onshow;block=begin;when [view.allowed_user]=1]
				[onshow;block=begin;when [conf.valideur.enabled;noerr]=1]
				<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=to_approve" class="butAction">[langs.transnoentities(SendToBeApprove)]</a></div>
				[onshow;block=end]
			<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=modif" class="butAction">[langs.transnoentities(Reopen)]</a></div>
			[onshow;block=end]
		[onshow;block=end]
		
		<!-- TODO check permission -->
		[onshow;block=begin;when [missionorder.status]=[TMissionOrder.STATUS_TO_APPROVE]]
			[onshow;block=begin;when [view.can_accept]=1]
			<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=approve" class="butAction">[langs.transnoentities(Accept)]</a></div>
			<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=refuse" class="butActionDelete">[langs.transnoentities(Refuse)]</a></div>
			[onshow;block=end]
		[onshow;block=end]
		
		[onshow;block=begin;when [view.can_create_ndfp]=1]
		<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=create_ndfp" class="butAction">[langs.transnoentities(CreateNdfp)]</a></div>
		[onshow;block=end]
		
		<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=clone" class="butAction">[langs.transnoentities(ToClone)]</a></div>
		
		
		[onshow;block=begin;when [view.allowed_user]=1]
		<div class="inline-block divButAction"><a href="[view.urlcard]?id=[missionorder.getId()]&action=delete" class="butActionDelete">[langs.transnoentities(Delete)]</a></div>
		[onshow;block=end]
		
		
	[onshow;block=end]
</div>
[onshow;block=end]
