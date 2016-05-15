<?php
//
//

// include the helper file
require_once(dirname(__FILE__) . '/../componenthelper.inc.php');

// respect the name
$scheduledowntime_component_name = "scheduledowntime";

// run the initialization function
scheduledowntime_component_init();

////////////////////////////////////////////////////////////////////////
// COMPONENT INIT FUNCTIONS
////////////////////////////////////////////////////////////////////////

function scheduledowntime_component_init()
{
    global $scheduledowntime_component_name;

    //boolean to check for latest version
    $versionok = scheduledowntime_component_checkversion();

    //component description
    $desc = gettext("This component allows administrators to submit downtimes. ");

    if (!$versionok)
        $desc = "<b>" . gettext("Error: This component requires Nagios XI 2009R1.2B or later.") . "</b>";

    //all components require a few arguments to be initialized correctly.
    $args = array(

        // need a name
        COMPONENT_NAME => $scheduledowntime_component_name,
        COMPONENT_VERSION => '1.2',
        COMPONENT_DATE => '3/223/2015',

        // informative information
        COMPONENT_AUTHOR => "IT Convergence",
        COMPONENT_DESCRIPTION => $desc,
        COMPONENT_TITLE => "ITC Schedule Downtime",
    );

    // Register this component with XI
    register_component($scheduledowntime_component_name, $args);

    // Register the addmenu function
    if ($versionok) {
        register_callback(CALLBACK_MENUS_INITIALIZED, 'scheduledowntime_component_addmenu');
    }
}


///////////////////////////////////////////////////////////////////////////////////////////
// MISC FUNCTIONS
///////////////////////////////////////////////////////////////////////////////////////////

function scheduledowntime_component_checkversion()
{

    if (!function_exists('get_product_release'))
        return false;
    //requires greater than 2009R1.2
    if (get_product_release() < 114)
        return false;

    return true;
}

function scheduledowntime_component_addmenu($arg = null)
{
    global $scheduledowntime_component_name;
    //retrieve the URL for this component
    $urlbase = get_component_url_base($scheduledowntime_component_name);
    //figure out where I'm going on the menu
    $mi = find_menu_item(MENU_HOME, "menu-home-acknowledgements", "id");
    if ($mi == null) //bail if I didn't find the above menu item
        return;

    $order = grab_array_var($mi, "order", ""); //extract this variable from the $mi array
    if ($order == "")
        return;

    $neworder = $order + 0.1; //determine my menu order

    //add this to the main home menu
    add_menu_item(MENU_HOME, array(
        "type" => "link",
        "title" => gettext("ITC Schedule Downtime"),
        "id" => "menu-home-scheduledowntime",
        "order" => $neworder,
        "opts" => array(
            //this is the page the menu will actually point to.
            //all of my actual component workings will happen on this script
            "href" => $urlbase . "/sch_down.php",
        )
    ));

}

FUNCTION get_hosts_option_list(){
    $option_list = '';
        #$option_list .='<option value="0" selected>Select Host..</option>';
        #Guido: This function call all the existing hosts this function return a simple xml object.
    $hosts = get_xml_host_objects();
        #Guido: the count variable is really important!! the first array only contains the total of records found.
        $count = 1;
    foreach($hosts as $data){
          # This is critical !!, the function get_xml_host_objects just return an xml simple object, we need to convert the xml object to an array.
          $data =(array)$data;
          #Guido: start extracting the host_names or values after the first loop.
          if ($count>1){
                          /*
                          Example $data structure by now the xxx represent the values
                          Array
                                        (   [@attributes] = &gt; Array([id] =&gt; xx)
                                                [instance_id] =&gt; x
                                                [host_name] =&gt; xxx
                                                [is_active] =&gt; xx
                                                [config_type] =&gt; xx
                                                [alias] =&gt; xxx
                                                [display_name] =&gt; xxxxx
                                                [address] =&gt; xxxxxxx
                                                [check_interval] =&gt; xx
                                                [retry_interval] =&gt; xx
                                                [max_check_attempts] =&gt; xx
                                                [first_notification_delay] =&gt; xx
                                                [notification_interval] =&gt; xx
                                                [passive_checks_enabled] =&gt; xx
                                                [active_checks_enabled] =&gt; xx
                                                [notifications_enabled] =&gt; xx
                                                [notes] =&gt; xxx
                                                [notes_url] =&gt; xxx
                                                [action_url] =&gt; xxx
                                                [icon_image] =&gt; xxx
                                                [icon_image_alt] =&gt; xxx
                                                [statusmap_image] =&gt; xxx)
                          */
        $option_list .='<option value="'.$data['host_name'].'">'.$data['host_name'].'</option>';
          }
          $count = $count + 1;
    }
        return $option_list;
}
