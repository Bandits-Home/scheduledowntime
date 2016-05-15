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
        COMPONENT_VERSION => '1.0',
        COMPONENT_DATE => '3/2/2015',
        // informative information
        COMPONENT_AUTHOR => "IT Convergence",
        COMPONENT_DESCRIPTION => $desc,
        COMPONENT_TITLE => "Mass Remove Downtimes",

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
        "title" => gettext("Schedule a Downtime"),
        "id" => "menu-home-scheduledowntime",
        "order" => $neworder,
        "opts" => array(
            //this is the page the menu will actually point to.
            //all of my actual component workings will happen on this script
            "href" => $urlbase . "/sch_down.php",
        )
    ));
}