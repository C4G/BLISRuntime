<?php
#
# Lists patient test history in printable format
#
/*
$load_time = microtime(); 
$load_time = explode(' ',$load_time); 
$load_time = $load_time[1] + $load_time[0]; 
$page_start = $load_time; 
*/

include("redirect.php");
include("includes/db_lib.php");
include("includes/script_elems.php");
include("includes/page_elems.php");
LangUtil::setPageId("reports");

include("../users/accesslist.php");
 if(!(isLoggedIn(get_user_by_id($_SESSION['user_id']))))
	header( 'Location: home.php' );

$date_from = "";
$date_to = "";
$hidePatientName = 0;

if(isset($_REQUEST['yf'])) {
	$date_from = $_REQUEST['yf']."-".$_REQUEST['mf']."-".$_REQUEST['df'];
	$date_to = $_REQUEST['yt']."-".$_REQUEST['mt']."-".$_REQUEST['dt'];
}
else {
	$date_from = date("Y-m-d");
	$date_to = $date_from;
}

# Helper function to fetch test history records
function get_records_to_print($lab_config, $patient_id) {
	global $date_from, $date_to;
	$retval = array();
	if(isset($_REQUEST['ip']) && $_REQUEST['ip'] == 0) {
		# Do not include pending tests
		$query_string =
			"SELECT t.* FROM test t, specimen sp ".
			"WHERE t.result <> '' ".
			"AND t.specimen_id=sp.specimen_id ".
			"AND sp.patient_id=$patient_id ";
		if(isset($_REQUEST['yf']))
			$query_string .= "AND (sp.date_collected BETWEEN '$date_from' AND '$date_to') ";
		$query_string .= "ORDER BY sp.date_collected DESC";
	
	}
	else {
		# Include pending tests
		$query_string =
			"SELECT t.* FROM test t, specimen sp ".
			"WHERE t.specimen_id=sp.specimen_id ".
			"AND sp.patient_id=$patient_id ";
		if(isset($_REQUEST['yf']))
			$query_string .= "AND (sp.date_collected BETWEEN '$date_from' AND '$date_to') ";
		$query_string .= "ORDER BY sp.date_collected DESC";		
	
	}
	
	$resultset = query_associative_all($query_string, $row_count);
	
	if(count($resultset) == 0 || $resultset == null)
		return $retval;
	
	foreach($resultset as $record) {
		$test = Test::getObject($record);
		$hide_patient_name = TestType::toHidePatientName($test->testTypeId);
		
		if( $hide_patient_name == 1 )
					$hidePatientName = 1;
		
		$specimen = get_specimen_by_id($test->specimenId);
		$retval[] = array($test, $specimen, $hide_patient_name);		
	}
	
	return $retval;
}

$lab_config_id = $_REQUEST['location'];
$patient_id = $_REQUEST['patient_id'];
DbUtil::switchToLabConfig($lab_config_id);
$lab_config = get_lab_config_by_id($lab_config_id);
$report_id = $REPORT_ID_ARRAY['reports_testhistory.php'];
$report_config = $lab_config->getReportConfig($report_id);
$margin_list = $report_config->margins;
for($i = 0; $i < count($margin_list); $i++) {
	$margin_list[$i] = ($SCREEN_WIDTH * $margin_list[$i] / 100);
}
?>
<html>
<head>

<style type="text/css"> 
	.btn {
		color:white; 
		background-color:#9fc748;/*#3B5998;*/ 
		border-style:none; 
		font-weight:bold; 
		font-size:14px; 
		height:25px; 
		/*width:60px;*/
		cursor:pointer;
	}
	
</style> 

<?php
$script_elems = new ScriptElems();
$script_elems->enableJQuery();
$script_elems->enableTableSorter();
$script_elems->enableDragTable();
$script_elems->enableLatencyRecord();
$script_elems->enableEditInPlace();
$page_elems = new PageElems();
?>

<script type="text/javascript" src="../js/nicEdit.js"></script>
<script type='text/javascript'>

var curr_orientation = 0;

function export_as_word(div_id) {
	document.getElementById('printhead').innerHTML=" ";
	var content = $('#'+div_id).html();
	$('#word_data').attr("value", content);
	$('#word_format_form').submit();
}

function print_content(div_id) {
	/*
	var DocumentContainer = document.getElementById(div_id);
	var WindowObject = window.open("", "PrintWindow", "toolbars=no,scrollbars=yes,status=no,resizable=yes");
	var html_code = DocumentContainer.innerHTML;
	var do_landscape = $("input[name='do_landscape']:checked").attr("value");
	if(do_landscape == "Y")
		html_code += "<style type='text/css'> #report_config_content {-moz-transform: rotate(-90deg) translate(-300px); } </style>";
	WindowObject.document.writeln(html_code);
	WindowObject.document.close();
	WindowObject.focus();
	WindowObject.print();
	WindowObject.close();
	*/
	$("#myNicPanel").hide();
	javascript:window.print();
}

function fetch_report() {
	var yf = $('#yyyy_from').attr("value");
	var mf = $('#mm_from').attr("value");
	var df = $('#dd_from').attr("value");
	var yt = $('#yyyy_to').attr("value");
	var mt = $('#mm_to').attr("value");
	var dt = $('#dd_to').attr("value");
	var ip = 0;
	if($('#ip').is(":checked"))
		ip = 1;
	$('#fetch_progress').show();
	var url_string = "reports_testhistory.php?location=<?php echo $lab_config_id; ?>&patient_id=<?php echo $patient_id; ?>&yf="+yf+"&mf="+mf+"&df="+df+"&yt="+yt+"&mt="+mt+"&dt="+dt+"&ip="+ip;
	window.location=url_string;
}

$(document).ready(function() {
	<?php
	if(isset($_REQUEST['ip']) && $_REQUEST['ip'] == 1) {
	?>
	$('#ip').attr("checked", "true");
	<?php
	}
	?>
	$('#report_content_table1').tablesorter();
	$('.editable').editInPlace({
		callback: function(unused, enteredText) {
			return enteredText; 
		},
		show_buttons: false,
		bg_over: "FFCC66",
		field_type: "textarea"
	});
	$("input[name='do_landscape']").click( function() {
		change_orientation();
	});
	var myNicEditor = new nicEditor();
    myNicEditor.setPanel('myNicPanel');
    myNicEditor.addInstance('patient_table');
});

function change_orientation() {
	var do_landscape = $("input[name='do_landscape']:checked").attr("value");
	
	if(do_landscape == "Y" && curr_orientation == 0) {
		$('#report_config_content').removeClass("portrait_content");
		$('#report_config_content').addClass("landscape_content");
		curr_orientation = 1;
	}
	
	if(do_landscape == "N" && curr_orientation == 1) {
		$('#report_config_content').removeClass("landscape_content");
		$('#report_config_content').addClass("portrait_content");
		curr_orientation = 0;
	}
}

$(document).ready(function(){
  // Reset Font Size
  var originalFontSize = $('#report_content').css('font-size');
   $(".resetFont").click(function(){
  $('#report_content').css('font-size', originalFontSize);
  $('#report_content table').css('font-size', originalFontSize);
  $('#report_content table th').css('font-size', originalFontSize);
  });
  // Increase Font Size
  $(".increaseFont").click(function(){
  	var currentFontSize = $('#report_content').css('font-size');
 	var currentFontSizeNum = parseFloat(currentFontSize, 10);
    var newFontSize = currentFontSizeNum*1.1;
		$('#report_content').css('font-size', newFontSize);
	$('#report_content table').css('font-size', newFontSize);
	$('#report_content table th').css('font-size', newFontSize);
	return false;
  });
  // Decrease Font Size
  $(".decreaseFont").click(function(){
  	var currentFontSize = $('#report_content').css('font-size');
 	var currentFontSizeNum = parseFloat(currentFontSize, 10);
    var newFontSize = currentFontSizeNum*0.9;
	$('#report_content').css('font-size', newFontSize);
	$('#report_content table').css('font-size', newFontSize);
	$('#report_content table th').css('font-size', newFontSize);
	return false;
  });
  
   $(".bold").click(function(){
  	 var selObj = window.getSelection();
		alert(selObj);
		selObj.style.fontWeight='bold';
	return false;
  });
});
</script>
<style type="text/css">
p.main {text-align:justify;}
</style>

</head>
<body>
<div id='options_header' style="font-family: Arial;" >
<form name='word_format_form' id='word_format_form' action='export_word.php' method='post' target='_blank'>
	<input type='hidden' name='data' value='' id='word_data' />
	<input type='hidden' name='lab_id' value='<?php echo $lab_config_id; ?>' id='lab_id'>
</form>
<?php
$today = date("Y-m-d");
$today_array = explode("-", $today);
$monthago_date = date("Y-m-d", strtotime(date("Y-m-d", strtotime($today)) . " -1 months"));
$monthago_array = explode("-", $monthago_date);
?>
<table class='no_border'>
	<tr valign='top'>
	<td>
		<?php echo LangUtil::$generalTerms['FROM_DATE']; ?>
	</td>
	<td>
			<?php
			$name_list = array("yyyy_from", "mm_from", "dd_from");
			$id_list = $name_list;
			if(!isset($_REQUEST['yf'])) {
				$value_list = $monthago_array;
			}
			else {
				$value_list = array($_REQUEST['yf'], $_REQUEST['mf'], $_REQUEST['df']);
			}
			$page_elems->getDatePickerPlain($name_list, $id_list, $value_list); 
			?>
	</td>
	<td>
	&nbsp;&nbsp;&nbsp;&nbsp;
			<input type='button' onclick="javascript:print_content('report_content');" value='<?php echo LangUtil::$generalTerms['CMD_PRINT']; ?>'></input>
			
	</td>
	<td>
		<table class='no border'>
	<tr valign='top'>
		
	<td>
	<input type='radio' name='do_landscape' value='N'<?php
			//if($report_config->landscape == false) echo " checked ";
			echo " checked ";
			?>>Portrait</input>
	</td>
	<td>
		<input type='radio' name='do_landscape' value='Y' <?php
			//if($report_config->landscape == true) echo " checked ";
			?>>Landscape</input>
	</td>
	</tr>
	</table>
	</td>
	
	<td>
		<input type='checkbox' name='ip' id='ip'></input> 
		<?php echo LangUtil::$pageTerms['MSG_INCLUDEPENDING']; ?>
	</td>
	<td>
		<input type='button' onclick="javascript:fetch_report();" value='<?php echo LangUtil::$generalTerms['CMD_VIEW']; ?>'></input>
		</td><td><span id='fetch_progress' style='display:none'>
			&nbsp;&nbsp;&nbsp;
			<?php $page_elems->getProgressSpinner(LangUtil::$generalTerms['CMD_FETCHING']); ?>
		</span>
	</td>
</tr>
<tr >
	<td>
			&nbsp;&nbsp;
			<?php echo LangUtil::$generalTerms['TO_DATE']; ?>
	</td>
	<td>
			<?php
			$name_list = array("yyyy_to", "mm_to", "dd_to");
			$id_list = $name_list;
			if(!isset($_REQUEST['yf'])) {
				$value_list = $today_array;
			}
			else {
				$value_list = array($_REQUEST['yt'], $_REQUEST['mt'], $_REQUEST['dt']);
			}
			$page_elems->getDatePickerPlain($name_list, $id_list, $value_list);
			?>
	</td>
	<td>
	&nbsp;&nbsp;
	Font
	</td>
	<td>
	<table class='no border'>
	<tr valign='top'><td>
	<input  type='button' class="increaseFont" value='Increase' title="Increase Font-size"></input> <br>
	</td>
	<td>
	<input type='button' class="decreaseFont" value='Decrease' title="Decrease Font-size"></input> <br>
	<!--<input type='button' class="bold" value='Bold' title="Bold"></input> <br>-->
	
	</td>
	</tr>
	</table>
	</td>
	<td>
	&nbsp;&nbsp;
	<input type='button' onclick="javascript:export_as_word('report_word_content');" value='Export Word Document' title='<?php echo LangUtil::$generalTerms['CMD_EXPORTWORD']; ?>'></input>
	</td>
	<td>
	&nbsp;&nbsp;
	<input type='button' onclick="javascript:window.close();" value='Close' title='<?php echo LangUtil::$generalTerms['CMD_CLOSEPAGE']; ?>'></input>
	</td>
	
	</tr>
</table>
<hr>
</div>
<div id='report_content'>
<link rel='stylesheet' type='text/css' href='css/table_print.css' />
<style type='text/css'>
div.editable {
	/*padding: 2px 2px 2px 2px;*/
	margin-top: 2px;
	width:900px;
	height:20px;
}

div.editable input {
	width:700px;
}
div#printhead {
position: fixed; top: 0; left: 0; width: 100%; height: 100%;
padding-bottom: 5em;
margin-bottom: 100px;
display:none;
}

@media all
{
  .page-break { display:none; }
}
@media print
{
	#options_header { display:none; }
	/* div#printhead {	display: block;
  } */
  div#docbody {
  margin-top: 5em;
  }
}

.landscape_content {-moz-transform: rotate(90deg) translate(300px); }

.portrait_content {-moz-transform: translate(1px); rotate(-90deg) }
</style>
<style type='text/css'>
	<?php $page_elems->getReportConfigCss($margin_list); ?>
</style>
<?php $align=$report_config->alignment_header;?>
<div id='report_config_content' style='display:block;'>
<div id="docbody" name="docbody">
<div id='logo' >
<?php
# If hospital logo exists, include it
$logo_path = "../logos/logo_".$lab_config_id.".jpg";
$logo_path2 = "../ajax/logo_".$lab_config_id.".jpg";
$logo_path1="../../logo_".$lab_config_id.".jpg";


if(file_exists($logo_path1) === true)
{	copy($logo_path1,$logo_path);
	?>
	<img src='<?php echo "logos/logo_".$lab_config_id.".jpg"; ?>' alt="Big Boat" height='140px'    ></src>
	<?php
}
else if(file_exists($logo_path) === true)
{
?>
	<img src='<?php echo "logos/logo_".$lab_config_id.".jpg"; ?>' alt="Big Boat" height='140px' width='140px'></src>
	<?php
}
?>
</div>
<!--//If condition for the font size
<STYLE>H3 {FONT-SIZE: <?php echo $size; ?>}</STYLE>-->
<div id="report_word_content">
<div id="date_section" >

<?php $align=$report_config->alignment_header;?>
<h3 align="<?php echo $align; ?>"><?php echo $report_config->headerText; ?><?php #echo LangUtil::$pageTerms['MENU_PHISTORY']; ?></h3>
<h4 align="<?php echo $align; ?>"><?php echo $report_config->titleText; ?></h4>
</div>
<?php
if(isset($_REQUEST['yf']))
{
	echo "<br>";
	if($date_from == $date_to) {
		echo LangUtil::$generalTerms['DATE'].": ".DateLib::mysqlToString($date_from);
	}
	else {	
		echo LangUtil::$generalTerms['FROM_DATE'].": ".DateLib::mysqlToString($date_from);
		echo " | ";
		echo LangUtil::$generalTerms['TO_DATE'].": ".DateLib::mysqlToString($date_to);
	}
}
?>

<br>
<?php
$patient = get_patient_by_id($patient_id);
if($patient == null)
{
	echo LangUtil::$generalTerms['PATIENT_ID']." $patient_id ".LangUtil::$generalTerms['MSG_NOTFOUND'];
}
else
{
	# Fetch test entries to print in report
	$record_list = get_records_to_print($lab_config, $patient_id); 
	# If single date supplied, check if-
	# 1. Physician name is the same for all
	# 2. Patient daily number is the same for all
	# 3. All tests were completed or not
	$physician_same = false;
	$daily_number_same = false;
	$all_tests_completed = false;
	if($date_from == $date_to) {
		$physician_same = true;
		$daily_number_same = true;
		$all_tests_completed = true;
		$record_count = 0;
		$previous_physician = "";
		$previous_daily_num = "";
		$count_list= count($record_list);
		
		foreach($record_list as $record_set) {
			$value = $record_set;
			$test = $value[0];
			//check for test_id if its in the array
			//http://www.w3schools.com/php/func_array_in_array.asp
			$specimen = $value[1];
			if( $hidePatientName == 0) 
				$hidePatientName = $value[2];
				
			if($record_count != 0) {
				if(strcasecmp($previous_physician, $specimen->getDoctor()) != 0) {
					$physician_same = false;
				}
				if(strcasecmp($previous_daily_num, $specimen->getDailyNumFull()) != 0) {
					$daily_number_same = false;
				}
				if($test->isPending() === true) {
					$all_tests_completed = false;
				}
				if($physician_same === false && $daily_number_same === false && $all_tests_completed === false)
					break;
			}
			$previous_physician = $specimen->getDoctor();
			$previous_daily_num = $specimen->getDailyNumFull();
			$record_count++;
		}
	}
	?>
	<div id="printhead" name="printhead">
		<?php
			if($report_config->usePatientName == 1) {
				echo $patient->name; 
				echo "\n";?><br><?php
			}
			if($report_config->useAge == 1) {
				echo $patient->getAge(); 
				echo "\n";?><br><?php
			}
			if($report_config->useGender == 1) {
				echo $patient->sex; 
				echo "\n";?><br><?php
			}
			?>
	</div>
	<table class='print_entry_border'>
		<tbody>
			<?php
			if($report_config->usePatientId == 1) {
				?>
				<tr valign='top'>
					<td><?php echo LangUtil::$generalTerms['PATIENT_ID']; ?></td>
					<td><?php echo $patient->getSurrogateId(); ?></td>
				</tr>
				<?php
			}
			if($report_config->useDailyNum == 1 && $daily_number_same === true) {
				?>
				<tr valign='top'>
					<td><?php echo LangUtil::$generalTerms['PATIENT_DAILYNUM']; ?></td>
					<td><?php echo $previous_daily_num; ?></td>
				</tr>
				<?php 
			}
			if($report_config->usePatientRegistrationDate == 1) {
				?>
				<tr valign='top'>
					<td><?php echo "Registration Date"; ?></td>
					<td><?php echo $patient->regDate ?></td>
				</tr>
				<?php
			}
			if($report_config->usePatientAddlId == 1) {
				?>
				<tr valign='top'>
					<td><?php echo LangUtil::$generalTerms['ADDL_ID']; ?></td>
					<td><?php echo $patient->getAddlId(); ?></td>
				</tr>
				<?php 
			}
			if( ($report_config->usePatientName == 1) && ($hidePatientName != 1) ) {
				?>
				<tr valign='top'>
					<td><?php echo LangUtil::$generalTerms['NAME']; ?></td>
					<td><?php echo $patient->name; ?></td>
				</tr>
				<?php
			}
			if($report_config->useAge == 1) {
				?>
				<tr valign='top'>
					<td><?php echo LangUtil::$generalTerms['AGE']; ?></td>
					<td><?php echo $patient->getAge(); ?></td>
				</tr>
				<?php
			}
			if($report_config->useGender == 1) {
				?>			
				<tr valign='top'>	
					<td><?php echo LangUtil::$generalTerms['GENDER']; ?></td>
					<td><?php echo $patient->sex; ?></td>
				</tr>
				<?php
			}
			if($report_config->useDob == 1) {
				?>
				<tr valign='top'>
					<td><?php echo LangUtil::$generalTerms['DOB']; ?></td>
					<td><?php echo $patient->getDob(); ?></td>
				</tr>
				<?php 
			}
			# Patient Custom fields here
			$custom_field_list = $lab_config->getPatientCustomFields();
			
			foreach($custom_field_list as $custom_field) {
				if(in_array($custom_field->id, $report_config->patientCustomFields)) {	
					$field_name = $custom_field->fieldName;				
					?>
					<tr valign='top'>
					<?php
					echo "<td>";
					echo $field_name;
					echo "</td>";
					$custom_data = get_custom_data_patient_bytype($patient->patientId, $custom_field->id);
					echo "<td>";
					if($custom_data == null) {
						echo "-";
					}
					else {
						$field_value = $custom_data->getFieldValueString($lab_config->id, 2);
						if(trim($field_value) == "")
							$field_value = "-";
						echo $field_value;
					}
					echo "</td>";					
					?>
					</tr>
					<?php
				}
			}
			if($report_config->useDoctor == 1 && $physician_same === true) {
				?>
				<tr valign='top'>
					<td><?php echo LangUtil::$generalTerms['DOCTOR']; ?></td>
					<td><?php echo $previous_physician; ?></td>
				</tr>
				<?php 
			}
			?>
		</tbody>
	</table>
	<br>
	<?php 
	if($all_tests_completed === true && count($record_list) != 0) {
		echo LangUtil::$pageTerms['MSG_ALLTESTSCOMPLETED']; 
	}
	else {
		echo LangUtil::$generalTerms['TESTS']; 
	}
	?>
	<?php 
	if(count($record_list) == 0) {
		echo LangUtil::$generalTerms['MSG_NOTFOUND'];
	}
	else { 
		if(1) { 
			?>
			<div id="myNicPanel" style="width: 525px;"></div>
			<div id="patient_table">
				<table class='print_entry_border draggable' id='report_content_table1'>
					<thead>
						<tr valign='top'>
						<?php 
				if($report_config->useSpecimenAddlId != 0) {
					echo "<th>".LangUtil::$generalTerms['SPECIMEN_ID']."</th>";
				}
				if($report_config->useDailyNum == 1 && $daily_number_same === false) {
					echo "<th>".LangUtil::$generalTerms['PATIENT_DAILYNUM']."</th>";
				}
				if($report_config->useSpecimenName == 1) {
					echo "<th>".LangUtil::$generalTerms['TYPE']."</th>";
				}
				if($report_config->useDateRecvd == 1) {
					echo "<th>".LangUtil::$generalTerms['R_DATE']."</th>";
				}
				# Specimen Custom fields headers here
				$custom_field_list = $lab_config->getSpecimenCustomFields();
				foreach($custom_field_list as $custom_field) {
					$field_name = $custom_field->fieldName;
					$field_id = $custom_field->id;
					if(in_array($field_id, $report_config->specimenCustomFields)) {
						echo "<th>".$field_name."</th>";
					}
				}
				if($report_config->useTestName == 1) {
					echo "<th>".LangUtil::$generalTerms['TEST'];
					echo "</th>";
				}
				if($report_config->useComments == 1) {
					echo "<th>".LangUtil::$generalTerms['COMMENTS']."</th>";
				}
				if($report_config->useReferredTo == 1) {
					echo "<th>".LangUtil::$generalTerms['REF_TO']."</th>";
				}
				if($report_config->useDoctor == 1 && $physician_same === false) {
					echo "<th>".LangUtil::$generalTerms['DOCTOR']."</th>";
				}
				if($report_config->useMeasures == 1)
					echo "<th>".LangUtil::$generalTerms['MEASURES']."</th>";
				if($report_config->useResults == 1)
					echo "<th>".LangUtil::$generalTerms['RESULTS']."</th>";
				if($report_config->useRange == 1)
					echo "<th>".LangUtil::$generalTerms['RANGE']."</th>";
				if($report_config->useEntryDate == 1) {
					echo "<th>".LangUtil::$generalTerms['E_DATE']."</th>";
				}
				if($report_config->useRemarks == 1) {
					echo "<th>".LangUtil::$generalTerms['RESULT_COMMENTS']."</th>";
				}
				if($report_config->useEnteredBy == 1) {
					echo "<th>".LangUtil::$generalTerms['ENTERED_BY']."</th>";
				}
				if($report_config->useVerifiedBy == 1) {
					echo "<th>".LangUtil::$generalTerms['VERIFIED_BY']."</th>";
				}
				if($report_config->useStatus == 1 && $all_tests_completed === false) {
					echo "<th>".LangUtil::$generalTerms['SP_STATUS']."</th>";
				}
				?>
				</tr>
			</thead>
			<tbody>
			<?php
			if(isset($_REQUEST['sid'])) {
				# Called after result entry for a single specimen
				$value = array($_REQUEST['sid'], $_REQUEST['tid']);
				$record_list = array();
				$record_list[] = $value;
				$data_list=array();
			}
			foreach($record_list as $record_set) {
				$value = $record_set;
				$test = $value[0];
				$specimen = $value[1];
				$id=$test->testTypeId;
				$clinical_data=get_clinical_data_by_id($test->testTypeId)
				?>
				<tr valign='top'>
				<?php
				if($report_config->useSpecimenAddlId != 0)
				{
					echo "<td>";
					$specimen->getAuxId();
					echo "</td>";
				}
				if($clinical_data!='')
				{
					$data_list[$id]=$clinical_data;
				}
				if($report_config->useDailyNum == 1 && $daily_number_same === false)
				{
					echo "<td>".$specimen->getDailyNum()."</td>";
				}
				
				if($report_config->useSpecimenName == 1)
				{
					echo "<td>".get_specimen_name_by_id($specimen->specimenTypeId)."</td>";
				}
				if($report_config->useDateRecvd == 1)
				{
					echo "<td>".DateLib::mysqlToString($specimen->dateRecvd)."</td>";
				}
				# Specimen Custom fields here
				$custom_field_list = $lab_config->getSpecimenCustomFields();
				foreach($custom_field_list as $custom_field)
				{
					if(in_array($custom_field->id, $report_config->specimenCustomFields))
					{
						echo "<td>";
						$custom_data = get_custom_data_specimen_bytype($specimen->specimenId, $custom_field->id);
						if($custom_data == null)
						{
							echo "-";
						}
						else
						{
							$field_value = $custom_data->getFieldValueString($lab_config->id, 1);
							if($field_value == "" or $field_value == null) 
							$field_value = "-";
							echo $field_value; 
						}
						echo "</td>";
					}
				}
				if($report_config->useTestName == 1)
				{
					echo "<td >".get_test_name_by_id($test->testTypeId)."</td>";
				}
				
				
				if($report_config->useComments == 1)
				{
					echo "<td>";
					echo $specimen->getComments();
					echo "</td>";
				}
				if($report_config->useReferredTo == 1)
				{
					echo "<td>".$specimen->getReferredToName()."</td>";
				}
				if($report_config->useDoctor == 1 && $physician_same === false)
				{
					$doc=$specimen->getDoctor();
					echo "<td>".$doc."</td>";
				}
				if($report_config->useMeasures == 1) {
					echo "<td>";
					echo $test->getMeasureList();
					echo "</td>";
				}
				if($report_config->useResults == 1) {
					echo "<td>";
					if(trim($test->result) == "")
						echo LangUtil::$generalTerms['PENDING_RESULTS'];
					else if($report_config->useMeasures == 1)
						echo $test->decodeResultWithoutMeasures();
					else
						echo $test->decodeResult();
					echo "</td>";
				}
				
				if($report_config->useRange == 1)
				{
					echo "<td>";
					if($test->isPending() === true)
						echo "N/A";
					else
					{
						$test_type = TestType::getById($test->testTypeId);
						$measure_list = $test_type->getMeasures();
						
                                                $submeasure_list = array();
                $comb_measure_list = array();
               // print_r($measure_list);
                
                foreach($measure_list as $measure)
                {
                    
                    $submeasure_list = $measure->getSubmeasuresAsObj();
                    //echo "<br>".count($submeasure_list);
                    //print_r($submeasure_list);
                    $submeasure_count = count($submeasure_list);
                    
                    if($measure->checkIfSubmeasure() == 1)
                    {
                        continue;
                    }
                        
                    if($submeasure_count == 0)
                    {
                        array_push($comb_measure_list, $measure);
                    }
                    else
                    {
                        array_push($comb_measure_list, $measure);
                        foreach($submeasure_list as $submeasure)
                           array_push($comb_measure_list, $submeasure); 
                    }
                }
                $measure_list = $comb_measure_list;
                                                
						foreach($measure_list as $measure) {
							echo "<br>";
							$type=$measure->getRangeType();
							if($type==Measure::$RANGE_NUMERIC) {
								$range_list_array=$measure->getRangeString($patient);
								$lower=$range_list_array[0];
								$upper=$range_list_array[1];
								$unit=$measure->unit;
								if(stripos($unit,",")!=false) {	
									echo "(";
									$units=explode(",",$unit);
									$lower_parts=explode(".",$lower);
									$upper_parts=explode(".",$upper);
				
									if($lower_parts[0]!=0) {
										echo $lower_parts[0];
										echo $units[0];
									}
									
									if($lower_parts[1]!=0) {
										echo $lower_parts[1];
										echo $units[1];
									}
									echo " - ";
				
									if($upper_parts[0]!=0) {
										echo $upper_parts[0];
										echo $units[0];
									}
									
									if($upper_parts[1]!=0) {
										echo $upper_parts[1];
										echo $units[1];
									}
									echo ")";
								} else if(stripos($unit,":")!=false) {
									$units=explode(":",$unit);
									echo "(";	
									echo $lower;
									?><sup><?php echo $units[0]; ?></sup> - 
									<?php echo $upper;?> <sup> <?php echo $units[0]; ?> </sup>
									<?php
									echo " ".$units[1].")";
								} else {	
									echo "(";		
									echo $lower; ?>-<?php echo $upper; 
									echo " ".$measure->unit.")";
								}?>
								&nbsp;&nbsp;	
								<?php
							} else {
								if($measure->unit=="")
									$measure->unit="-";
								echo "&nbsp;&nbsp;&nbsp;". $measure->unit;
							}
							echo "<br>";
						}
					}
					echo "</td>";
				}
				
				if($report_config->useEntryDate == 1)
				{
					echo "<td>";
				
					if(trim($test->result) == "")
						echo "-";
					else {
						$ts_parts = explode(" ", $test->timestamp);
						echo DateLib::mysqlToString($ts_parts[0]);
					}
					echo "</td>";
				}
				
				if($report_config->useRemarks == 1) {
					echo "<td>".$test->getComments()."</td>";
				}
				
				if($report_config->useEnteredBy == 1) {
					echo "<td>".$test->getEnteredBy()."</td>";
				}
				
				if($report_config->useVerifiedBy == 1) {
					echo "<td>".$test->getVerifiedBy()."</td>";
				}
				
				if($report_config->useStatus == 1 && $all_tests_completed === false) {
					echo "<td>".$test->getStatus()."</td>";
				}
				
				?>
				</tr>
			<?php
			}
			?>
			</tbody>
		</table>
		</div>
		<br><br>
		<?php if($report_config->useClinicalData == 1) {
 		
			if(count($data_list)==1&&count(record_list)==1) {
				?>
				<b>
				Clinical Data:
				</b>
				<?php  
				foreach($data_list as $key=>$value) {
				
					if(stripos($value,"!#!")===0) {
						$data=substr($value,3);
						$dat=explode("%%%",$value);
						$text=substr($dat[0],3);
						$table=$dat[1];
					}
					else if(stripos($value,"%%%")===0) {
						$text="";
						$table=substr($value,3);
					}
					else {
						$text=$value;//substr($value,3);
						$table="";
					}
					
					if($text!="")
						echo $text;
						
					if($table!="") {
						$contents=explode("###",$table);
						$name_array=$contents[0];
						$value_array=$contents[1];
						$name=explode(",",$name_array);
						$value=explode(",",$value_array);
					}
					?><table>
					<?php 
					for($i=0;$i<count($name);$i++) {
						if($name[$i]!=" ") {
							?>
							<tr>
							<td>
							<?php echo $name[$i];?>
							</td>
							<td>
							<?php echo $value[$i];?>
							</td>
							</tr>
							<?php 
						}
					}
					?>
					</table>
					<?php
				}
				?>
				<br><br>
				<?php
			}
			else if( count($data_list) > 0 ) {
				$bullet=1;
				foreach($data_list as $key=>$value) {
					echo $bullet++ ?>). <b>Test Name:</b>
					<?php echo get_test_name_by_id ($key); ?>
					<br><b>
					Clinical Data:
					</b>
					<?php
					if(stripos($value,"!#!")===0) {
						$data=substr($value,3);
						$dat=explode("%%%",$value);
						$text=substr($dat[0],3);
						$table=$dat[1];
					}
					else if(stripos($value,"%%%")===0) {
						$table=substr($value,3);
						$text="";
					} else {
						$text=$value;//substr($value,3);
						$table="";
					}
					
				if($text!="")
					echo $text;
					
				if($table!=""&&stripos($value,"%%%")!=0) {
					$contents=explode("###",$table);
					$name_array=$contents[0];
					$value_array=$contents[1];
					$name=explode(",",$name_array);
					$value=explode(",",$value_array);
			
					?>
					<table>
					<?php for($i=0;$i<count($name);$i++) {
							if($name[$i]!="") {
								?>
								<tr>
								<td>
								<?php echo $name[$i];?>
								</td>
								<td>
								<?php echo $value[$i];?>
								</td>
								</tr>
								<?php 
							}
						}
					?>
					</table> <?php }?>
					<br><br>
					<?php
				}
			}
		}
	} else {
			if(isset($_REQUEST['sid'])) {
				# Called after result entry for a single specimen
				$value = array($_REQUEST['sid'], $_REQUEST['tid']);
				$record_list = array();
				$record_list[] = $value;
				$data_list=array();
			}
			
			foreach($record_list as $record_set) {
				$value = $record_set;
				$test = $value[0];
				$specimen = $value[1];
				$id=$test->testTypeId;
				$clinical_data=get_clinical_data_by_id($test->testTypeId);
				?>	
				<?php
				
				if($report_config->useSpecimenName == 1) {
					echo "<h3>";
					echo LangUtil::$generalTerms['TYPE']."&nbsp;&#45;&nbsp;";
					echo get_specimen_name_by_id($specimen->specimenTypeId)."</h3>";
				}
				
				if($report_config->useTestName == 1) {
					echo "<h3>";
					echo LangUtil::$generalTerms['TEST']."&nbsp;&#45;&nbsp;";
					echo get_test_name_by_id($test->testTypeId)."</h3>";
				}
				
				if($report_config->useSpecimenAddlId != 0) {
					echo LangUtil::$generalTerms['SPECIMEN_ID']."&nbsp;&#45;&nbsp;";
					echo $specimen->getAuxId();
					echo "<br>";
				}
				
				if($clinical_data!='') {
					$data_list[$id]=$clinical_data;
				}
				if($report_config->useDailyNum == 1 && $daily_number_same === false) {
					echo LangUtil::$generalTerms['PATIENT_DAILYNUM']."&nbsp;&#45;&nbsp;";
					echo $specimen->getDailyNum()."<br>";
				}
				if($report_config->useDateRecvd == 1) {
					echo LangUtil::$generalTerms['R_DATE']."&nbsp;&#45;&nbsp;";
					echo DateLib::mysqlToString($specimen->dateRecvd)."<br>";
				}
				# Specimen Custom fields headers here
				$custom_field_list = $lab_config->getSpecimenCustomFields();
				foreach($custom_field_list as $custom_field) {
					$field_name = $custom_field->fieldName;
					$field_id = $custom_field->id;
					if(in_array($field_id, $report_config->specimenCustomFields)) {
						echo $field_name;
					}
				}
				# Specimen Custom fields here
				$custom_field_list = $lab_config->getSpecimenCustomFields();
				foreach($custom_field_list as $custom_field) {
					if(in_array($custom_field->id, $report_config->specimenCustomFields))
					{
						echo "<br>";
						$custom_data = get_custom_data_specimen_bytype($specimen->specimenId, $custom_field->id);
						if($custom_data == null) {
							echo "-";
						}
						else {
							$field_value = $custom_data->getFieldValueString($lab_config->id, 1);
							if($field_value == "" or $field_value == null) 
							$field_value = "-";
							echo $field_value; 
						}
						echo "<br>";
					}
				}
				
				if($report_config->useComments == 1) {
					echo LangUtil::$generalTerms['COMMENTS']."&nbsp;&#45;&nbsp;";
					echo $specimen->getComments()."<br>";
				}
				
				if($report_config->useReferredTo == 1) {
					echo LangUtil::$generalTerms['REF_TO']."&nbsp;&#45;&nbsp;";
					echo $specimen->getReferredToName()."<br>";
				}
				
				if($report_config->useDoctor == 1 && $physician_same === false) {
					echo LangUtil::$generalTerms['DOCTOR']."&nbsp;&#45;&nbsp;";
					$doc=$specimen->getDoctor();
					echo $doc."<br>";
				}
				
				if($report_config->useMeasures == 1)
					echo LangUtil::$generalTerms['MEASURES']."<br>";
				
				if($report_config->useResults == 1) {
					echo LangUtil::$generalTerms['RESULTS']."<br>";
					
					if(trim($test->result) == "") {
						echo LangUtil::$generalTerms['PENDING_RESULTS'];
					}
					else if($report_config->useMeasures == 1) {
						echo $test->decodeResultWithoutMeasures();
					}
					else
                                            
                                            //NC3065
						echo $test->decodeResult();
                                                //echo $test->decodeResults();
                                            //NC3065
					echo "<br>";
				}
				
				if($report_config->useEntryDate == 1) {
					echo LangUtil::$generalTerms['E_DATE']."&nbsp;&#45;&nbsp;";
					
					if(trim($test->result) == "")
						echo "-";
						
					else {
						$ts_parts = explode(" ", $test->timestamp);
						echo DateLib::mysqlToString($ts_parts[0]);
					}
					echo "<br>";
				}
				
				if($report_config->useRemarks == 1) {
					echo LangUtil::$generalTerms['RESULT_COMMENTS']."&nbsp;&#45;&nbsp;";
					echo $test->getComments()."<br>";
				}
				
				if($report_config->useEnteredBy == 1) {
					echo LangUtil::$generalTerms['ENTERED_BY']."&nbsp;&#45;&nbsp;";
					echo $test->getEnteredBy()."<br>";
				}
				
				if($report_config->useVerifiedBy == 1) {
					echo LangUtil::$generalTerms['VERIFIED_BY']."&nbsp;&#45;&nbsp;";
					echo $test->getVerifiedBy()."<br>";
				}
				
				if($report_config->useStatus == 1 && $all_tests_completed === false) {
					echo LangUtil::$generalTerms['SP_STATUS']."&nbsp;&#45;&nbsp;";
					echo $test->getStatus()."<br>";
				}
			
			}
			?>
		
		<br><br>
		<?php 
		if($report_config->useClinicalData == 1) { 		
			if(count($data_list)==1&&count(record_list)==1) {
				?>
				<b>
					Clinical Data:
					</b>
					<?php  
					foreach($data_list as $key=>$value) {
						if( stripos($value,"!#!")===0 ) {
							$data=substr($value,3);
							$dat=explode("%%%",$value);
							$text=substr($dat[0],3);
							$table=$dat[1];
						}
						else if(stripos($value,"%%%")===0) {
							$text="";
							$table=substr($value,3);
						}
						else {
							$text=$value;//substr($value,3);
							$table="";
						}
			
						if($text!="")
							echo $text;
							
						if($table!="") {
							$contents=explode("###",$table);
							$name_array=$contents[0];
							$value_array=$contents[1];
							$name=explode(",",$name_array);
							$value=explode(",",$value_array);
						}
						?><table>
						<?php 
							for($i=0;$i<count($name);$i++) {
								if($name[$i]!=" ") {
									?>
									<tr>
									<td>
									<?php echo $name[$i];?>
									</td>
									<td>
									<?php echo $value[$i];?>
									</td>
									</tr>
									<?php 
								}
							}
						?>
						</table>
						<?php
			
					}
				?>
				<br><br>
				<?php
			}
			else {
				$bullet=1;
				foreach($data_list as $key=>$value) {
					echo $bullet++ ?>). <b>Test Name:</b>
					<?php echo get_test_name_by_id ($key); ?>
					<br><b>
					Clinical Data:
					</b>
					<?php
					if(stripos($value,"!#!")===0) {
						$data=substr($value,3);
						$dat=explode("%%%",$value);
						$text=substr($dat[0],3);
						$table=$dat[1];
					}
					else if(stripos($value,"%%%")===0) {
						$table=substr($value,3);
						$text="";
					} else {
						$text=$value;//substr($value,3);
						$table="";
					}
				
					if($text!="")
						echo $text;
			
					if($table!=""&&stripos($value,"%%%")!=0) {
						$contents=explode("###",$table);
						$name_array=$contents[0];
						$value_array=$contents[1];
						$name=explode(",",$name_array);
						$value=explode(",",$value_array);
						
						?>
						<table>
						<?php 
						for($i=0;$i<count($name);$i++) {
							if($name[$i]!="") {
								?>
								<tr>
								<td>
								<?php echo $name[$i];?>
								</td>
								<td>
								<?php echo $value[$i];?>
								</td>
								</tr>
								<?php 
							}
						}
						?>
						</table> <?php }?>
						<br><br>
						<?php
					}
				}
			}
		}
	}
}

if(count($record_list) != 0)
{
	$latest_record = $record_list[0];
	$earliest_record = $record_list[count($record_list)-1];
	$latest_specimen = $latest_record[1];
	$earliest_specimen = $earliest_record[1];
	$latest_collection_parts = explode("-", $latest_specimen->dateCollected);
	$earliest_collection_parts = explode("-", $earliest_specimen->dateCollected);
	if(!isset($_REQUEST['yf'])) {
		?>
		<script type='text/javascript'>
		$(document).ready(function(){
			$('#dd_from').attr("value", "<?php echo $earliest_collection_parts[2]; ?>");
			$('#mm_from').attr("value", "<?php echo $earliest_collection_parts[1]; ?>");
			$('#yyyy_from').attr("value", "<?php echo $earliest_collection_parts[0]; ?>");
			$('#dd_to').attr("value", "<?php echo $latest_collection_parts[2]; ?>");
			$('#mm_to').attr("value", "<?php echo $latest_collection_parts[1]; ?>");
			$('#yyyy_to').attr("value", "<?php echo $latest_collection_parts[0]; ?>");
			var date_from = "<?php echo DateLib::mysqlToString($earliest_specimen->dateCollected); ?>";
			var date_to = "<?php echo DateLib::mysqlToString($latest_specimen->dateCollected); ?>";
			var html_string = "";
			if(date_from == date_to)
			{
				html_string = "<br><?php echo LangUtil::$generalTerms['DATE'].": "; ?>"+date_from;		
			}
			else
			{
				html_string = "<br><?php echo LangUtil::$generalTerms['FROM_DATE'].": "; ?>"+date_from+" | <?php echo LangUtil::$generalTerms['TO_DATE'].": "; ?>"+date_to;
			}
			
			$('#date_section').html(html_string);
		});

		function change_to_bold() {
			$("#myPara").css("font-style","bold");
		} 
	</script>
	<?php
	}
}
?>
<div class='editable' title='Click to Edit'>
</div>
<div class='editable' title='Click to Edit'>
</div>
<div class='editable' title='Click to Edit'>
</div>
<!--p class="main">
............................................-->
<?php 
$new_footer_part="............................................";
$footerText=explode(";" ,$report_config->footerText);
$designation=explode(";" ,$report_config->designation);
$lab_config_id=$_SESSION['lab_config_id'];

?>

<table width=100% border="0" class="no_border" ">
<tr>
<?php for($j=0;$j<count($footerText);$j++) {?>
<td <?php if($lab_config_id==234) {?>style="font-size:14pt;"<?php }?> ><?php echo $new_footer_part; ?></td>
<?php }?>
</tr>
<tr>
<?php for($j=0;$j<count($footerText);$j++) {?>
<td align="center" <?php if($lab_config_id==234) {?>style="font-size:14pt;"<?php }?>><?php echo $footerText[$j]; ?></td>
<?php }?>
</tr>
<tr>
<?php for($j=0;$j<count($designation);$j++) {?>
<td align="center"<?php if($lab_config_id==234) {?>style="font-size:14pt;"<?php }?> ><?php echo $designation[$j]; ?></td>
<?php }
/*
$load_time = microtime(); 
$load_time = explode(' ',$load_time); 
$load_time = $load_time[1] + $load_time[0]; 
$page_end = $load_time; 
$final_time = ($page_end - $page_start); 
$page_load_time = number_format($final_time, 4, '.', ''); 
echo("Page generated in " . $page_load_time . " seconds"); 
*/
?>
</tr>
</table>
</div>
</div>
</div>
</div>
</body>

</html>