<?php
//
// Schedule a Downtime
//

require_once(dirname(__FILE__) . '/../../common.inc.php');

// Initialization stuff
pre_init();
init_session();

// Grab GET or POST variables and do prereq/auth checks
grab_request_vars();
check_prereqs();
check_authentication(false);

$title = gettext("Nagios XI - Schedule a Downtime");

do_page_start(array("page_title" => $title), true);

/**
 * Convert a PHP array containing recurring downtime config to a string appropriate for
 * downtime's schedule.cfg
 *
 * @param $arr
 *
 * @return string
 */
function recurringdowntime_array_to_cfg($arr)
{
    if (count($arr) == 0) {
        return "";
    }
    $min = $arr["minutes"];
    $hour = $arr["hour"];
    $month = $arr["month"];
    $day = $arr["day"];
    $year = $arr["year"];
    $duration = $arr["duration"];
    $comment = $arr["comment"];
    $user = $arr["user"];
    $start = mktime($hour,$min,0,$month,$day,$year);
    $end = $start + ($duration * 60);
    $nowt = shell_exec('date +%s');
    $now = trim($nowt);

    $cfg_str = "[$now] ";
    if ($arr["schedule_type"] == "host"){
	$host = $arr["host_name"];
	$cfg_str .= "SCHEDULE_HOST_DOWNTIME;$host;$start;$end;1;0;$duration;$user;$comment";
	if ($arr["svcalso"] == "1"){
		$cfg_str .= "\n [$now] SCHEDULE_HOST_SVC_DOWNTIME;$host;$start;$end;1;0;$duration;$user;$comment";
	}
    }elseif ($arr["schedule_type"] == "service"){
	$host = $arr["host_name"];
	$service = $arr["service_description"];
	//SCHEDULE_SVC_DOWNTIME;host1;service1;1110741500;1110748700;0;0;7200;Some One;Some Downtime Comment\n
	$cfg_str .= "SCHEDULE_SVC_DOWNTIME;$host;$service;$start;$end;1;0;$duration;$user;$comment";
    }elseif ($arr["schedule_type"] == "hostgroup"){
        $hg = $arr["hostgroup_name"];
	$cfg_str .= "SCHEDULE_HOSTGROUP_HOST_DOWNTIME;$hg;$start;$end;1;0;$duration;$user;$comment";
	if ($arr["svcalso"] == "1"){
                $cfg_str .= "\n [$now] SCHEDULE_HOSTGROUP_SVC_DOWNTIME;$hg;$start;$end;1;0;$duration;$user;$comment";
        }
    }elseif ($arr["schedule_type"] == "servicegroup"){
	$sg = $arr["servicegroup_name"];
	$cfg_str .= "SCHEDULE_SERVICEGROUP_SVC_DOWNTIME;$sg;$start;$end;1;0;$duration;$user;$comment";
    }
    $cfg_str .= "\n";
    return $cfg_str;
}

/**
 * Write the configuration to disk
 *
 * @param $cfg
 *
 * @return bool
 */
function recurringdowntime_write_cfg($cfg)
{
    if (is_array($cfg)) {
        $cfg_str = recurringdowntime_array_to_cfg($cfg);
    } else {
        $cfg_str = $cfg;
    }
    $fh = fopen('/usr/local/nagios/var/rw/nagios.cmd', "a") or die(gettext("Error: Could not open downtime config file for writing."));
    fwrite($fh, $cfg_str);
    fclose($fh);
    return true;
}

/**
 * Generate random-ish sid for new entries
 *
 * @return string
 */
function recurringdowntime_generate_sid()
{
    return md5(uniqid(microtime()));
}

route_request();

function route_request()
{
    $mode = grab_request_var("mode", "");
    switch ($mode) {
        case "add":
            recurringdowntime_add_downtime();
            break;
        case "edit":
            recurringdowntime_add_downtime($edit = true);
            break;
        case "delete":
            recurringdowntime_delete_downtime();
            break;
        default:
            recurringdowntime_show_downtime();
            break;
    }
}

/**
 * @param bool $edit
 */
function recurringdowntime_add_downtime($edit = false) {
    global $request;

    // check session
    check_nagios_session_protector();

    if ($edit && !$request["sid"]) {
        $edit = false;
    }

    if ($edit) {
        $arr_cfg = recurringdowntime_get_cfg();
        $formvars = $arr_cfg[$request["sid"]];
        $days = array("mon", "tue", "wed", "thu", "fri", "sat", "sun");
        $selected_days = split(",", $formvars["days_of_week"]);
        unset($formvars["days_of_week"]);
        for ($i = 0; $i < 7; $i++) {
            if (in_array($days[$i], $selected_days)) {
                $formvars["days_of_week"][$i] = "on";
            }
        }

        if (count($formvars) == 0) {
            echo "<strong>" . gettext('The requested schedule id (sid) is not valid.') . "</strong>";
            exit;
        }
        if ($arr_cfg[$request["sid"]]["schedule_type"] == "hostgroup") {
            $form_mode = "hostgroup";
        } elseif ($arr_cfg[$request["sid"]]["schedule_type"] == "servicegroup") {
            $form_mode = "servicegroup";
        } elseif ($arr_cfg[$request["sid"]]["schedule_type"] == "service") {
            $form_mode = "service";
        } else {
            $form_mode = "host";
        }
    } else {
        $form_mode = $request["type"];
        // host or host_name should work
        $formvars["host_name"] = grab_request_var("host_name", grab_request_var("host", ""));
        //$formvars["host_name"] = isset($request["host_name"]) ? $request["host_name"] : "";
        $formvars["service_description"] = grab_request_var("service_description", grab_request_var("service", ""));
        $formvars["hostgroup_name"] = isset($request["hostgroup_name"]) ? $request["hostgroup_name"] : "";
        $formvars["servicegroup_name"] = isset($request["servicegroup_name"]) ? $request["servicegroup_name"] : "";

        $formvars["duration"] = "60";
    }

    $errors = array();

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // handle form
        if ($form_mode == "host") {
            if (is_readonly_user(0)) {
                $errors[] = gettext("Read only users cannot add schedule recurring downtime");
            }
//            if (empty($request["hosts"]))
//                $errors[] = gettext("Please enter a host name.");
//            else if (!is_authorized_for_host(0, $request["host_name"])) {
//                $errors[] = gettext("The host you specified is not valid for your user account.");
//            }
        } else if ($form_mode == "service") {
            if (is_readonly_user(0)) {
                $errors[] = gettext("Read only users cannot add schedule recurring downtime");
            }
            if (empty($request["host_name"]))
                $errors[] = gettext("Please enter a host name.");
            else if (!is_authorized_for_host(0, $request["host_name"]) && empty($request["service_description"])) {
                $errors[] = gettext("The host you specified is not valid for your user account.");
            }
            if (empty($request["service_description"]))
                $errors[] = gettext("Please enter a service name.");
            else {
                if (strstr($request["service_description"], "*")) {
                    $search_str = "lk:" . str_replace("*", "%", $request["service_description"]);
                } else {
                    $search_str = $request["service_description"];
                }

                if (!is_authorized_for_service(0, $request["host_name"], $search_str)) {
                    $errors[] = gettext("The service you specified is not valid for your user account.");
                }
            }
        } else if ($form_mode == "servicegroup") {
            if (empty($request["servicegroup_name"]))
                $errors[] = gettext("Please enter a servicegroup name.");
            else if (!is_authorized_for_servicegroup(0, $request["servicegroup_name"])) {
                $errors[] = gettext("The servicegroup you specified is not valid for your user account.");
            }
        } else {
            if (empty($request["hostgroup_name"]))
                $errors[] = gettext("Please enter a hostgroup name.");
            else if (!is_authorized_for_hostgroup(0, $request["hostgroup_name"])) {
                $errors[] = gettext("The hostgroup you specified is not valid for your user account.");
            }
        }
        $required = array(
            "time" => gettext("Please enter the start time for this downtime event."),
            "duration" => gettext("Please enter the duration of this downtime event.")
        );
        foreach ($required as $field => $errval) {
            if (empty($request[$field])) {
                $errors[] = $errval;
            }
        }

        if (!empty($request["time"])) {
            $exp = '/^(20|21|22|23|[01]\d|\d)(([:][0-5]\d){1,2})$/';
            if (!preg_match($exp, $request["time"])) {
                $errors[] = gettext("Please enter a valid time in 24-hour format, e.g. 21:00.");
            }
        }
        if (!empty($request["duration"])) {
            if (!is_numeric($request["duration"])) {
                $errors[] = gettext("Please enter a valid duration time in seconds, e.g. 120.");
            }
        }
        if (!count($errors) > 0) {
            $new_cfg = array(
                "user" => $_SESSION["username"],
                "comment" => $request["comment"],
                "duration" => $request["duration"],
                "hour" => substr($request["time"],0,2),
                "minutes" => substr($request["time"],-2),
                "month" => $request["element_2_1"],
                "day" => $request["element_2_2"],
                "year" => $request["element_2_3"],
            );

            if (isset($request["svcalso"])) {
                $new_cfg["svcalso"] = 1;
            } else {
		$new_cfg["svcalso"] = 0;
	    }
            if ($edit) {
                $sid = $request["sid"];
            } else {
                $sid = recurringdowntime_generate_sid();
            }

            if ($form_mode == "host") {
                $new_cfg["schedule_type"] = "host";
//                $new_cfg["host_name"] = $request["host_name"];
		foreach($request["hosts"] as $value) {
			$new_cfg["host_name"] = $value;
			$cfg[$sid] = $new_cfg;
			recurringdowntime_write_cfg($new_cfg);
			sleep(2);
			//echo $value;
		}
            } elseif ($form_mode == "service") {
                $new_cfg["schedule_type"] = "service";
                $new_cfg["service_description"] = $request["service_description"];
                $new_cfg["host_name"] = $request["host_name"];
		$cfg[$sid] = $new_cfg;
		//print_r($new_cfg);
		recurringdowntime_write_cfg($new_cfg);
            } elseif ($form_mode == "servicegroup") {
                $new_cfg["schedule_type"] = "servicegroup";
                $new_cfg["servicegroup_name"] = $request["servicegroup_name"];
		$cfg[$sid] = $new_cfg;
		recurringdowntime_write_cfg($new_cfg);
            } elseif ($form_mode == "hostgroup") {
                $new_cfg["schedule_type"] = "hostgroup";
                $new_cfg["hostgroup_name"] = $request["hostgroup_name"];
		$cfg[$sid] = $new_cfg;
		recurringdowntime_write_cfg($new_cfg);
            }

            //$cfg[$sid] = $new_cfg;
            //recurringdowntime_write_cfg($new_cfg);
	    ?><h1>Downtime(s) created</h1>
	    <?php
            //if ($request["return"]) {
            //    $go = $request["return"];
            //} else {
            //    $go = $_SERVER["PHP_SELF"];
            //}
            //echo "LOCATION: $go";
            //exit;
            //header("Location: $go");
            exit;
        } else {
            $formvars = $request;
        }
    }

    do_page_start(array("page_title" => gettext("Add Recurring Downtime Schedule")), true);
?>

<h1>Add a Downtime</h1>
<?php if (count($errors) > 0) { ?>
    <div id="message">
        <ul class="errorMessage">
            <?php foreach ($errors as $k => $msg) { ?>
                <li><?php echo $msg; ?></li>
            <?php } ?>
    </div>
<?php } ?>
<form name="myform" action="<?php echo htmlentities($_SERVER["REQUEST_URI"]); ?>" method="post">
<input type="hidden" name="return" value="<?php echo encode_form_val($request["return"]); ?>">
<?php echo get_nagios_session_protector(); ?>

<div class="sectionTitle"><?php echo gettext("Schedule Settings"); ?></div>

<p>
<table class="editDataSourceTable">
<tbody>
<?php if ($form_mode == "host") { ?>
<tr>
<td>Host(s):</td>
<td>
<SCRIPT TYPE="text/javascript" SRC="js/filterlist.js"></SCRIPT> 
<input type="text" id="filterHosts" style="display:inline-block;width:auto;min-width:225px;" placeholder="Hosts Filter..." onKeyUp="myfilter.set(this.value)">
<br><br>
<select name="hosts[]" id="hosts" size="15" style="display:inline-block;width:auto;min-width:225px;" multiple required>
<?php $options = get_hosts_option_list();
echo $options;
?>
</select>
<SCRIPT TYPE="text/javascript">
<!--
var myfilter = new filterlist(document.myform.hosts);
//-->
</SCRIPT>
<br><br></td>
</tr>
    <tr>
        <td valign="top">
            <label for="svcalso"><?php echo gettext("Services"); ?>:</label>
        </td>
        <td>
            <?php echo gettext("Include all services on this host?"); ?>
            <input id="svcalso" class="checkfield" type="checkbox" name="svcalso" value="1" checked="checked"/>
            <br><br>
        </td>
    </tr>

<?php } elseif ($form_mode == "service") { ?>
    <tr>
        <td valign="top">
            <label for="hostBox"><?php echo gettext("Host"); ?>:</label>
            <br class="nobr"/>
        </td>
        <td>
            <?php if ($edit || (isset($_GET["host_name"]) || isset($_GET["host"]))) { ?>
            <input disabled="disabled" id="hostBox" class="textfield" type="text" name="host_name"
                   value="<?php echo encode_form_val($formvars["host_name"]); ?>" size="25"/>
            <input type="hidden" name="host_name" value="<?php echo encode_form_val($formvars["host_name"]); ?>"/>
            <?php } else { ?>
            <input id="hostBox" class="textfield" type="text" name="host_name"
                   value="<?php echo encode_form_val($formvars["host_name"]); ?>" size="25"/>
                <script type="text/javascript">
                    $(document).ready(function () {
                        $("#hostBox").each(function () {
                            $(this).autocomplete({source: suggest_url + '?type=host', minLength: 1});

                            $(this).blur(function () {
                                var hostname = $("#hostBox").val();
                            });
                            $(this).change(function () {
                                var hostname = $("#hostBox").val();
                            });

                        });
                    });
                </script>
            <?php } ?>
            <br><?php echo gettext("The host associated with this schedule."); ?><br><br>
        </td>
    </tr>
    <tr>
        <td valign="top">
            <label for="serviceBox"><?php echo gettext("Service"); ?>:</label>
            <br class="nobr"/>
        </td>
        <td>
            <input id="serviceBox" class="textfield" type="text" name="service_description"
                   value="<?php echo encode_form_val($formvars["service_description"]); ?>" size="25"/>
            <br><?php echo gettext("Optional service associated with this schedule."); ?>
            <?php
            if (is_admin()) {
                ?>
                <?php echo gettext("A wildcard can be used to specify multiple matches"); ?> (e.g. 'HTTP*').
            <?php
            }
            ?>
            <br><br>
            <script type="text/javascript">
                $(document).ready(function () {
                    $("#serviceBox").each(function () {
                        $(this).focus(function () {
                            var hostname = $("#hostBox").val();
                            // TODO - we should destroy the old autocomplete here (but the function doesn't exist) , because multiple calls get made if the user goes back and changes the host name...
                            $(this).autocomplete({
                                source: suggest_url + '?type=services&host=' + hostname,
                                minLength: 1
                            });
                        });
                    });
                });
            </script>

        </td>
    </tr>
<?php } elseif ($form_mode == "servicegroup") { ?>
    <tr>
        <td valign="top">
            <label for="servicegroupBox"><?php echo gettext("Servicegroup"); ?>:</label>
        </td>
        <td>
            <?php if ($edit || isset($_GET["servicegroup_name"])) { ?>
            <input disabled="disabled" id="servicegroupBox" class="textfield" type="text" name="servicegroup_name"
                   value="<?php echo encode_form_val($formvars["servicegroup_name"]); ?>" size="25"/>
            <input type="hidden" name="servicegroup_name"
                   value="<?php echo encode_form_val($formvars["servicegroup_name"]); ?>"/>
            <?php } else { ?>
            <input id="servicegroupBox" class="textfield" type="text" name="servicegroup_name"
                   value="<?php echo encode_form_val($formvars["servicegroup_name"]); ?>" size="25"/>
            <br><?php echo gettext("The servicegroup associated with this schedule"); ?>.<br><br>
                <script type="text/javascript">
                    $(document).ready(function () {
                        $("#servicegroupBox").each(function () {
                            $(this).autocomplete({source: suggest_url + '?type=servicegroups', minLength: 1});
                        });
                    });
                </script>
            <?php } ?>
        </td>
    </tr>
<?php } else { ?>
    <tr>
        <td valign="top">
            <label for="hostgroupBox"><?php echo gettext("Hostgroup"); ?>:</label>
        </td>
        <td>
            <?php if ($edit || isset($_GET["hostgroup_name"])) { ?>
            <input disabled="disabled" id="hostgroupBox" class="textfield" type="text" name="hostgroup_name"
                   value="<?php echo encode_form_val($formvars["hostgroup_name"]); ?>" size="25"/>
            <input type="hidden" name="hostgroup_name"
                   value="<?php echo encode_form_val($formvars["hostgroup_name"]); ?>"/>
            <?php } else { ?>
            <input id="hostgroupBox" class="textfield" type="text" name="hostgroup_name"
                   value="<?php echo encode_form_val($formvars["hostgroup_name"]); ?>" size="25"/>
            <br><?php echo gettext("The hostgroup associated with this schedule"); ?>.<br><br>
                <script type="text/javascript">
                    $(document).ready(function () {
                        $("#hostgroupBox").each(function () {
                            $(this).autocomplete({source: suggest_url + '?type=hostgroups', minLength: 1});
                        });
                    });
                </script>
            <?php } ?>
        </td>
    </tr>
    <tr>
        <td valign="top">
            <label for="svcalso"><?php echo gettext("Services"); ?>:</label>
        </td>
        <td>
            <?php echo gettext("Include all services in this hostgroup?"); ?>
            <input id="svcalso" class="checkfield" type="checkbox" name="svcalso" value="1" checked="checked"/>
            <br><br>
        </td>
    </tr>
<?php } ?>
<tr>
    <td valign="top">
        <label for="commentBox"><?php echo gettext("Comment"); ?>:</label>
    </td>
    <td>
        <input id="commentBox" class="textfield" type="text" name="comment" size="60"/> <br>
        <?php echo gettext("An optional comment associated with this schedule"); ?>.<br><br>
    </td>
</tr>
<tr>
    <td valign="top">
        <label for="timeBox"><?php echo gettext("Start Time"); ?>:</label>
    </td>
    <td>
        <input id="timeBox" class="textfield" type="text" name="time" size="6"/> <br>
        <?php echo gettext("Time of day the downtime should start in 24-hr format"); ?> (e.g. 13:30).<br><br>
    </td>
</tr>

<script type="text/javascript">
    /*
     $(document).ready(function(){
     $('#timeBox').timepickr();
     });
     */
</script>

<tr>
    <td valign="top">
        <label for="durationBox"><?php echo gettext("Duration"); ?>:</label>
    </td>
    <td>
        <input id="durationBox" class="textfield" type="text" name="duration"
               value="<?php echo encode_form_val($formvars["duration"]); ?>" size=6/> <br>
        <?php echo gettext("Duration of the scheduled downtime in minutes"); ?>.<br><br>
    </td>
</tr>
<link rel="stylesheet" type="text/css" href="view.css" media="all">
<script type="text/javascript" src="js/view.js"></script>
<script type="text/javascript" src="js/calendar.js"></script>
<tr>
    <td valign="top">
        <label for="startdate"><?php echo gettext("Start Date"); ?>:</label>
    </td>
    <td>
              <span>  <input id="element_2_1" name="element_2_1" readonly="readonly" class="element text" size="2" maxlength="2" value="" type="text"> /
                      <label for="month">MM</label></span>
              <span>  <input id="element_2_2" name="element_2_2" readonly="readonly" class="element text" size="2" maxlength="2" value="" type="text"> /
                      <label for="day">DD</label></span>
              <span>  <input id="element_2_3" name="element_2_3" readonly="readonly" class="element text" size="4" maxlength="4" value="" type="text">
                      <label for="year">YYYY</label></span>
              <span id="calendar_2"><img id="cal_img_2" class="datepicker" src="images/calendar.gif" alt="Pick a date."></span>
              <script type="text/javascript">
                      Calendar.setup({
                      inputField       : "element_2_3",
                      baseField    : "element_2",
                      displayArea  : "calendar_2",
                      button           : "cal_img_2",
                      ifFormat         : "%B %e, %Y",
                      onSelect         : selectDate
                      });
              </script>
	<br><br>
    </td>
</tr>
<tr>
    <td></td>
    <td align="left">
        <input type="submit" name="submit" value="<?php echo gettext("Submit"); ?>"/>&nbsp;
        <input type="button" name="cancel" value="<?php echo gettext("Cancel"); ?>"
               onClick="javascript:document.location.href='<?php echo $request["return"]; ?>'"/>
    </td>
</tr>
</table>

<?php
    do_page_end(true);
}
//
// end function recurringdowntime_add_downtime()
//

//
// begin function show_downtime()
//
function recurringdowntime_show_downtime()
{
    global $request;
    global $lstr;

    do_page_start(array("page_title" => $lstr['RecurringDowntimePageTitle']), true);

    if (isset($request["host"])) {
        $host_tbl_header = gettext("Recurring Downtime for Host ") . $request["host"];
        $service_tbl_header = gettext("Recurring Downtime for Host ") . $request["host"];
        if (is_authorized_for_host(0, $request["host"])) {
            $host_data = recurringdowntime_get_host_cfg($request["host"]);
            $service_data = recurringdowntime_get_service_cfg($request["host"]);
        }
    } elseif (isset($request["hostgroup"])) {
        $hostgroup_tbl_header = gettext("Recurring Downtime for Hostgroup ") . $request["hostgroup"];
        if (is_authorized_for_hostgroup(0, $request["hostgroup"])) {
            $hostgroup_data = recurringdowntime_get_hostgroup_cfg($request["hostgroup"]);
        }
    } elseif (isset($request["servicegroup"])) {
        $servicegroup_tbl_header = gettext("Recurring Downtime for Servicegroup ") . $request["servicegroup"];
        if (is_authorized_for_servicegroup(0, $request["servicegroup"])) {
            $servicegroup_data = recurringdowntime_get_servicegroup_cfg($request["servicegroup"]);
        }
    }

    if (!isset($request["host"]) && !isset($request["hostgroup"]) && !isset($request["servicegroup"])) {
        /*
        $host_tbl_header = "Recurring Downtime for All Hosts";
        $hostgroup_tbl_header = "Recurring Downtime for All Hostgroups";
        $servicegroup_tbl_header = "Recurring Downtime for All Servicegroups";
        */
        $host_tbl_header = gettext("Host Schedules");
        $service_tbl_header = gettext("Service Schedules");
        $hostgroup_tbl_header = gettext("Hostgroup Schedules");
        $servicegroup_tbl_header = gettext("Servicegroup Schedules");
        $showall = true;
    }

    ?>
    <h1>Pick a Downtime Type</h1>

    <?php
    if (!isset($request["host"]) && !isset($request["hostgroup"]) && !isset($request["servicegroup"])) {
        ?>
        <!--
        <div><a href="<?php echo htmlentities($_SERVER["PHP_SELF"]); ?>?mode=add&type=host&return=<?php echo urlencode($_SERVER["REQUEST_URI"]); ?>">+ Add Host Downtime Schedule</a></div>
        <div><a href="<?php echo htmlentities($_SERVER["PHP_SELF"]); ?>?mode=add&type=hostgroup&return=<?php echo urlencode($_SERVER["REQUEST_URI"]); ?>">+ Add Hostgroup Downtime Schedule</a></div>
        <div><a href="<?php echo htmlentities($_SERVER["PHP_SELF"]); ?>?mode=add&type=servicegroup&return=<?php echo urlencode($_SERVER["REQUEST_URI"]); ?>">+ Add Servicegroup Downtime Schedule</a></div>
        //-->
        <p>
    <?php } ?>

    <?php
    if ($showall) {
        ?>
        <script type="text/javascript">
            $(document).ready(function () {
                $("#tabs").tabs();
            });
        </script>

        <div id="tabs">
        <ul>
            <li><a href="#host-tab"><?php echo gettext("Hosts"); ?></a></li>
            <li><a href="#service-tab"><?php echo gettext("Services"); ?></a></li>
            <li><a href="#hostgroup-tab"><?php echo gettext("Hostgroups"); ?></a></li>
            <li><a href="#servicegroup-tab"><?php echo gettext("Servicegroups"); ?></a></li>
        </ul>
    <?php
    }
    ?>
<!-- Host Tab Start -->
    <div id='host-tab'>
    <div style="margin-top:20px;"></div>
    <div class="infotable_title" style="float:left"><?php echo $host_tbl_header; ?></div>
    <?php if (!is_readonly_user(0)) { ?>
    <div style="clear: left; margin: 0 0 10px 0;"><a href="<?php echo htmlentities($_SERVER["PHP_SELF"]); ?>?mode=add&type=host&return=<?php echo urlencode($_SERVER["REQUEST_URI"]); ?>%23host-tab&nsp=<?php echo get_nagios_session_protector_id(); ?>"><img src="<?php echo theme_image("add.png"); ?>"> <?php echo gettext("Add a Downtime"); ?></a></div><?php } ?> 
    </div>
    <div style="margin-top:20px;"></div>
<!-- Host Tab End -->

<!-- Service Tab Start -->
    <div id='service-tab'>
    <div class="infotable_title" style="float:left"><?php echo $service_tbl_header; ?></div>
    <?php if (!is_readonly_user(0)) { ?>
    <div style="clear: left; margin: 0 0 10px 0;"><a href="<?php echo htmlentities($_SERVER["PHP_SELF"]); ?>?mode=add&type=service&return=<?php echo urlencode($_SERVER["REQUEST_URI"]); ?>%23service-tab&nsp=<?php echo get_nagios_session_protector_id(); ?>"><img src="<?php echo theme_image("add.png"); ?>"> <?php echo gettext("Add a Downtime"); ?></a></div><?php } ?>
    </div>
    <div style="margin-top:20px;"></div>
<!-- ServiceHost Tab End -->

<!-- Hostgroup Tab Start -->
    <div id='hostgroup-tab'>
    <div class="infotable_title" style="float:left"><?php echo $hostgroup_tbl_header; ?></div>
    <?php if (!is_readonly_user(0)) { ?>
    <div style="clear: left; margin: 0 0 10px 0;"><a href="<?php echo htmlentities($_SERVER["PHP_SELF"]); ?>?mode=add&type=hostgroup&return=<?php echo urlencode($_SERVER["REQUEST_URI"]); ?>%23hostgroup-tab&nsp=<?php echo get_nagios_session_protector_id(); ?>"><img src="<?php echo theme_image("add.png"); ?>"> <?php echo gettext("Add a Downtime"); ?></a></div><?php }  ?>
    </div>
    <div style="margin-top:20px"></div>
<!-- Hostgroup Tab End -->

<!-- Servicegroup Tab Start -->
    <div id='servicegroup-tab'>
    <div class="infotable_title" style="float:left"><?php echo $servicegroup_tbl_header; ?></div>
    <?php if (!is_readonly_user(0)) { ?>
    <div style="clear: left; margin: 0 0 10px 0;"><a href="<?php echo htmlentities($_SERVER["PHP_SELF"]); ?>?mode=add&type=servicegroup&return=<?php echo urlencode($_SERVER["REQUEST_URI"]); ?>%23servicegroup-tab&nsp=<?php echo get_nagios_session_protector_id(); ?>"><img src="<?php echo theme_image("add.png"); ?>"> <?php echo gettext("Add a Downtime"); ?></a></div><?php } ?>
    </div>
<!-- Servicegroup Tab End -->

    <?php { ?>
    </div> <?php }
    do_page_end(true);
}
//
// end function recurringdowntime_show_downtime()
