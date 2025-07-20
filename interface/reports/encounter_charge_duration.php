<?php
/**
 * Encounter Charge Duration Report
 *
 * Displays encounters with appointment duration, total charges, insurance and coding info.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../globals.php";
require_once "$srcdir/patient.inc.php";
require_once "$srcdir/options.inc.php";

use OpenEMR\Billing\BillingUtilities;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Services\FacilityService;
use ESign\Api as ESignApi;
use OpenEMR\Common\Twig\TwigContainer;

if (!AclMain::aclCheckCore('acct', 'rep_a')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Encounter Charge Duration")]);
    exit;
}

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

$facilityService = new FacilityService();

$form_from_date = isset($_POST['form_from_date']) ? DateToYYYYMMDD($_POST['form_from_date']) : date('Y-m-d');
$form_to_date = isset($_POST['form_to_date']) ? DateToYYYYMMDD($_POST['form_to_date']) : date('Y-m-d');
$form_provider = $_POST['form_provider'] ?? '';
$form_facility = $_POST['form_facility'] ?? '';
$form_uncoded = !empty($_POST['form_uncoded']);

?>
<html>
<head>
    <title><?php echo xlt('Encounter Charge Duration'); ?></title>
    <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>
    <script>
        $(function() {
            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
            });
        });

        function signAll() {
            var ids = [];
            $('.esign-button-encounter').each(function() {
                ids.push($(this).data('encounterid'));
            });
            if (!ids.length) { return; }
            var pw = prompt('<?php echo addslashes(xl('Password for eSign')); ?>');
            if (pw === null) { return; }
            ids.forEach(function(id){
                $.post('../esign/index.php?module=Encounter&action=esign_form_submit',
                    {encounterId:id, password:pw},
                    function(){}, 'json');
            });
        }
    </script>
</head>
<body class="body_top">
<span class='title'><?php echo xlt('Encounter Charge Duration'); ?></span>
<form method='post' id='report_form' action='encounter_charge_duration.php' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
<div id="report_parameters">
<table class="tableonly">
<tr>
<td>
  <table class='text'>
    <tr>
      <td class='col-form-label'><?php echo xlt('Facility'); ?>:</td>
      <td><?php dropdown_facility($form_facility, 'form_facility', false); ?></td>
      <td class='col-form-label'><?php echo xlt('From'); ?>:</td>
      <td><input type='text' name='form_from_date' id='form_from_date' class='datepicker form-control' size='10' value='<?php echo attr(oeFormatShortDate($form_from_date)); ?>' /></td>
      <td class='col-form-label'><?php echo xlt('To{{Range}}'); ?>:</td>
      <td><input type='text' name='form_to_date' id='form_to_date' class='datepicker form-control' size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>' /></td>
      <td class='col-form-label'><?php echo xlt('Provider'); ?>:</td>
      <td><?php generate_form_field(['data_type' => 10, 'field_id' => 'provider','empty_title' => '-- All Providers --'], $form_provider); ?></td>
      <td class='col-form-label'><?php echo xlt('Uncoded Only'); ?>:</td>
      <td><input type='checkbox' name='form_uncoded' value='1'<?php echo $form_uncoded ? ' checked' : ''; ?> /></td>
    </tr>
  </table>
</td>
<td class='h-100' align='left' valign='middle'>
  <table class='w-100 h-100' style='border-left:1px solid;'>
    <tr>
      <td>
        <div class="text-center">
          <div class="btn-group" role="group">
            <a href='#' class='btn btn-secondary btn-save' onclick="$('#form_refresh').val('true'); $('#report_form').submit();"><?php echo xlt('Submit'); ?></a>
            <a href='#' class='btn btn-secondary' onclick='signAll(); return false;'><?php echo xlt('Sign All'); ?></a>
          </div>
        </div>
      </td>
    </tr>
  </table>
</td>
</tr>
</table>
<input type='hidden' name='form_refresh' id='form_refresh' value='' />
</div>
</form>
<?php
if (!empty($_POST['form_refresh'])) {
    $sql = "SELECT fe.encounter, fe.date AS encdate, fe.pid, pd.fname, pd.lname, pd.pubpid, e.pc_duration, SUM(b.fee) AS charges
            FROM form_encounter AS fe
            LEFT JOIN openemr_postcalendar_events AS e ON fe.pid = e.pc_pid AND fe.date = e.pc_eventDate
            LEFT JOIN patient_data AS pd ON pd.pid = fe.pid
            LEFT JOIN billing AS b ON b.pid = fe.pid AND b.encounter = fe.encounter AND b.activity = 1 AND b.code_type != 'COPAY'
            WHERE fe.date >= ? AND fe.date <= ?";
    $binds = [$form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59'];
    if ($form_provider) {
        $sql .= " AND fe.provider_id = ?";
        $binds[] = $form_provider;
    }
    if ($form_facility !== '') {
        $sql .= " AND fe.facility_id = ?";
        $binds[] = $form_facility;
    }
    $sql .= " GROUP BY fe.encounter ORDER BY fe.date";
    $res = sqlStatement($sql, $binds);
    echo "<table class='table table-striped'>";
    echo "<thead class='thead-light'>";
    echo "<tr>";
    echo "<th>" . xlt('Date') . "</th>";
    echo "<th>" . xlt('Patient') . "</th>";
    echo "<th>" . xlt('Duration (min)') . "</th>";
    echo "<th>" . xlt('Charge') . "</th>";
    echo "<th>" . xlt('Insurance') . "</th>";
    echo "<th>" . xlt('Codes') . "</th>";
    echo "<th>" . xlt('') . "</th>";
    echo "</tr>";
    echo "</thead><tbody>";
    $esignApi = new ESignApi();
    while ($row = sqlFetchArray($res)) {
        $codesArr = BillingUtilities::getBillingByEncounter($row['pid'], $row['encounter'], 'code');
        $codeList = array_column($codesArr, 'code');
        if ($form_uncoded && !empty($codeList)) {
            continue;
        }
        $ins = getInsuranceNameByDate($row['pid'], substr($row['encdate'], 0, 10), 'primary');
        $duration = $row['pc_duration'] ? intval($row['pc_duration'])/60 : '';
        $button = $esignApi->createEncounterESign($row['encounter'])->buttonHtml();
        echo "<tr>";
        echo "<td>" . text(oeFormatShortDate(substr($row['encdate'],0,10))) . "</td>";
        echo "<td>" . text($row['lname'] . ', ' . $row['fname']) . "</td>";
        echo "<td>" . text($duration) . "</td>";
        echo "<td>" . text(sprintf('%0.2f',$row['charges'])) . "</td>";
        echo "<td>" . text($ins) . "</td>";
        echo "<td>" . text(implode(', ', $codeList)) . "</td>";
        echo "<td>" . $button . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}
?>
</body>
</html>
