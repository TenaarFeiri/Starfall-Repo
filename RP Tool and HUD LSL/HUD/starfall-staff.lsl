string function = "null";
integer pageLogs = 1; // For pagination of logs.
integer sensing;
integer pageSensor = 1; // Pagination of sensor results.
integer staffLinkMsgNum = -10;
float time = 30.0;
list dataSet; // Variable list for processing based on which function we're doing or what responses we receive.
list sensed;
list sensedNames;
string itemRecipient;
string query; // HTTP Request query
string url = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/inventory_staff_handler.php?";
key httpKey;
ping() { httpKey = llHTTPRequest(url, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], query);}
integer isInteger(string var)
{
    integer i;
    for (i=0;i<llStringLength(var);++i)
    {
        if(!~llListFindList(["1","2","3","4","5","6","7","8","9","0"],[llGetSubString(var,i,i)]))
        {
            return FALSE;
        }
    }
    return TRUE;
}
list mainMenu = [
    "Logs",
    "Items",
    "Money",
    "Inventory",
    "Characters",
    "Cancel"
];
list logsMenu = [
    "Chk Logs",
    "Srch Logs",
    "Cancel"
];
list itemMenu = [
    "Give Item",
    "Create Item",
    "Edit Item",
    "Srch Items",
    "List Items",
    "Cancel"
];
list moneyMenu = [
    "Give Money",
    "Remove Money",
    "Cancel"
];
list inventoryMenu = [
    "Chk Inv",
    "Cancel"
];
list characterMenu = [
    "Lookup",
    "Cancel"
];
integer Key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
integer channel;
clearAll()
{
    llSetTimerEvent(0);
    llListenRemove(listener);
    function = "null";
    dataSet = [];
    sensed = [];
    itemRecipient = "";
    sensedNames = [];
    pageLogs = 1;
    pageSensor = 1;
    sensing = FALSE;
    query = "";
}
list orderButtons(list buttons)
{
    return llList2List(buttons, -3, -1) + llList2List(buttons, -6, -4)
         + llList2List(buttons, -9, -7) + llList2List(buttons, -12, -10);
}
integer listener;
list paginateButtons(list buttons, integer page)
{
    if(page < 0)
    {
        page = 1;
    }
    integer firstNum = 9 * page - 9;
    integer lastNum = page + 9;    
    return llList2List(buttons, firstNum, lastNum) + ["<<", "Cancel", ">>"];
}
list paginateTargetButtons(list buttons, integer page)
{
    if(page < 0)
    {
        page = 1;
    }
    integer firstNum = 8 * page - 8;
    integer lastNum = page + 8;    
    return llList2List(buttons, firstNum, lastNum) + ["<<", "Cancel",  ">>", "Execute"];
}
default
{
    state_entry()
    {
        channel = Key2AppChan(llGetOwner(), 574);
    }
    
    http_response(key id, integer status, list meta, string data)
    {
        if(id != httpKey){return;}
        if(status != 200 && status != 201){return;}
        llOwnerSay(data);
        if(llGetSubString(data, 0, 3) == "err:")
        {
            llOwnerSay(llGetSubString(data, 4, -1));
            clearAll();
            return;
        }
        if(function == "chkLogs" || function == "srchLogs") // When we're browsing logs.
        {
            llOwnerSay(data);
            llSetTimerEvent(time);
            llDialog(llGetOwner(), function, orderButtons(["<<","Cancel",">>"]), channel);
        }
        else if(function == "giveItem")
        {
            llOwnerSay(data);
            if(~llSubStringIndex(data, "itemsuccess::"))
            {
               data = llDeleteSubString(data, 0, 12);
               string item = llGetSubString(data, 0, (llSubStringIndex(data, "::") - 1));
               data = llDeleteSubString(data, 0, (llSubStringIndex(data, "::") + 1));
               string tmp = llDumpList2String(llParseString2List(data, ["&&"], []), ", ");
               string amount = llList2String(dataSet, 1);
               llOwnerSay("You have given " + amount + "x " + item + " to " + tmp);
               dataSet = llList2List(dataSet, 2, -1);
               integer i = (llGetListLength(dataSet) - 1);
               integer x;
               key target;
               do
               {
                   target = (key)llList2String(dataSet, x);
                   llRegionSayTo(target, Key2AppChan(target, 1338), "::readyUpdate::" + (string)target);
                   llSleep(0.2);
                   llRegionSayTo(target, Key2AppChan(target, 1338), "::doUpdate::" + (string)target);
                   llRegionSayTo(target, 0, "You have been given " + amount + "x " + item + " by " + llKey2Name(llGetOwner()) + ". If you had no room for it in your inventory, it has been delivered into your bank storage.");
                }while(x++<=i);
                clearAll();
            }
        }
        else if(function == "giveMoney")
        {
            if(~llSubStringIndex(data, "moneysuccess::"))
            {
               data = llDeleteSubString(data, 0, (llSubStringIndex(data, "::") + 1));
               string tmp = llDumpList2String(llParseString2List(data, ["&&"], []), ", ");
               string amount = llList2String(dataSet, 0);
               llOwnerSay("You have given " + amount + " of money to " + tmp);
               dataSet = llList2List(dataSet, 1, -1);
               llOwnerSay(llList2CSV(dataSet));
               integer i = (llGetListLength(dataSet) - 1);
               integer x;
               key target;
               do
               {
                   target = (key)llList2String(dataSet, x);
                   llRegionSayTo(target, Key2AppChan(target, 1338), "::readyUpdate::" + (string)target);
                   llSleep(0.2);
                   llRegionSayTo(target, Key2AppChan(target, 1338), "::doUpdate::" + (string)target);
                   llRegionSayTo(target, 0, "You have been given " + amount + " Crowns by " + llKey2Name(llGetOwner()) + ".");
                }while(x++<=i);
                clearAll();
            }
        }
    }
    
    sensor(integer num)
    {
        if(!sensing)
        { // Don't do shit if I'm an idiot and we're not sensing!
            return;
        }
        integer i = 0;
        string name;
        do
        {
            if(llDetectedKey(i) != NULL_KEY && llAgentInExperience(llDetectedKey(i)))
            {
                sensed += [(string)llDetectedKey(i)];
                name = llBase64ToString(llGetSubString(llStringToBase64(llDetectedName(i)), 0, 31));
                sensedNames += [name];
            }
        }while(++i <= num);
        llSetTimerEvent(time);
        pageSensor = 1;
        llDialog(llGetOwner(), "Choose your target(s)!", orderButtons(paginateTargetButtons(sensedNames, pageSensor)), channel);
    }
    
    listen(integer c, string n, key id, string m)
    {
        if(m == "Cancel")
        {
            clearAll();
        }
        if(sensing)
        {
            if(m == "<<")
            {
                --pageSensor;
                if(pageSensor < 1)
                {
                    pageSensor = 1;
                }
                llDialog(llGetOwner(), "Pick who to give it to!", orderButtons(paginateTargetButtons(sensedNames, pageSensor)), channel);
            }
            else if(m == ">>")
            {
                ++pageSensor;
                if(pageSensor < 1)
                {
                    pageSensor = 1;
                }
                llDialog(llGetOwner(), "Pick who to give it to!", orderButtons(paginateTargetButtons(sensedNames, pageSensor)), channel);
            }
            else if(m == "Execute")
            {
                if(function == "giveItem")
                {
                    query = "func=addItemToMultiple&targets=" + llList2CSV(llList2List(dataSet, 2, -1)) + "&itemId=" + llList2String(dataSet, 0) + "&amount=" + llList2String(dataSet, 1);
                }
                else if(function == "giveMoney")
                {
                    query = "func=giveMoney&targets=" + llList2CSV(llList2List(dataSet, 1, -1)) + "&amount=" + llList2String(dataSet, 0);
                }
                ping();
            }
            else
            {
                llSetTimerEvent(time);
                integer i = llListFindList(sensedNames, [m]);
                if(i == -1)
                {
                    llOwnerSay("Not on the list!");
                    llDialog(llGetOwner(), "Pick who to give it to!", orderButtons(paginateTargetButtons(sensedNames, pageSensor)), channel);
                    return;
                }
                dataSet += [llList2String(sensed, i)];
                itemRecipient = itemRecipient + llList2String(sensedNames, i) + ", ";
                sensed = llDeleteSubList(sensed, i, i);
                sensedNames = llDeleteSubList(sensedNames, i, i);
                llOwnerSay("Targets: " + itemRecipient);
                llDialog(llGetOwner(), "Select your target(s)", orderButtons(paginateTargetButtons(sensedNames, pageSensor)), channel);
            }
        }
        else if(function == "srchLogs" && dataSet == [])
        {
            dataSet = [(string)m];
            query = "func=srchLogs&page=" + (string)pageLogs + "&search="+llList2String(dataSet, 0);
            ping();
        }
        else if(m == "<<")
        {
            if(function == "chkLogs" || function == "srchLogs")
            {
                if(pageLogs > 1)
                {
                    --pageLogs;
                }
                if(function == "chkLogs")
                {
                    query = "func=chkLogs&page=" + (string)pageLogs;
                }
                else if(function == "srchLogs")
                {
                    query = "func=srchLogs&page=" + (string)pageLogs + "&search="+llList2String(dataSet, 0);
                }
                ping();
            }
        }
        else if(m == ">>")
        {
            if(function == "chkLogs" || function == "srchLogs")
            {
                ++pageLogs;
                if(function == "chkLogs")
                {
                    query = "func=chkLogs&page=" + (string)pageLogs;
                }
                else if(function == "srchLogs")
                {
                    query = "func=srchLogs&page=" + (string)pageLogs + "&search="+llList2String(dataSet, 0);
                }
                ping();
            }
        }
        // FUNCTION SPECIFIC METHODS
        else if(function == "giveItem")
        {
            if(dataSet == [])
            {
                if(!isInteger(m))
                {
                    clearAll();
                    llDialog(llGetOwner(), m + " is not a valid integer.", ["OK"], channel);
                }
                llSetTimerEvent(time);
                dataSet += [(string)m];
                llTextBox(llGetOwner(), "How much do you want to give?", channel);
            }
            else if(llGetListLength(dataSet) == 1)
            {
                if(!isInteger(m))
                {
                    clearAll();
                    llDialog(llGetOwner(), m + " is not a valid integer.", ["OK"], channel);
                }
                llSetTimerEvent(time);
                dataSet += [(string)m];
                llDialog(llGetOwner(), "Give to ", ["Myself", "Other"], channel);
            }
            else if(llGetListLength(dataSet) == 2)
            {
                if(m == "Myself")
                {
                    // Just execute, no need to confirm! Staff knows what they're doing!
                    query = "func=addItemToMultiple&targets=" + (string)llGetOwner() + "&itemId=" + llList2String(dataSet, 0) + "&amount=" + llList2String(dataSet, 1);
                    dataSet += [(string)llGetOwner()];
                    ping();
                }
                else if(m == "Other")
                {
                    sensing = TRUE;
                    llSensor("", "", AGENT, 20, PI);
                }
            }
        }
        else if(function == "giveMoney")
        {
            if(llGetListLength(dataSet) > 0)
            {
                if(m == "Myself")
                {
                    dataSet += [(string)llGetOwner()];
                    query = "func=giveMoney&targets=" + (string)llGetOwner() + "&amount=" + llList2String(dataSet, 0);
                    ping();
                    return;
                }
                else
                {
                    sensing = TRUE;
                    llSensor("", "", AGENT, 20, PI);
                }
            }
            else if(llGetListLength(dataSet) == 0)
            {
                list tmp = llParseString2List(llStringTrim(m, STRING_TRIM), [" "], []);
                integer i = (llGetListLength(tmp) - 1);
                integer x = 0;
                string r;
                integer amount = 0;
                if(isInteger(llStringTrim(m, STRING_TRIM)))
                {
                    // If it's an integer, do sensor!
                    amount = (integer)m;
                }
                do
                {
                    r = llStringTrim(llGetSubString(llStringTrim(llList2String(tmp, x), STRING_TRIM), 0, -2), STRING_TRIM);
                    if(!isInteger(r))
                    {
                        llDialog(llGetOwner(), "Can't give money; invalid amount entered. Try this format, where X Y Z are replaced with numbers: Xg Ys Zc.\n\n Your input: " + m, ["OK"], channel);
                        clearAll();
                        return;
                    }
                    if(llToLower(llGetSubString(llList2String(tmp, x), -1, -1)) == "g")
                    {
                        amount = amount + ((integer)r * 1000);
                    }
                    else if(llToLower(llGetSubString(llList2String(tmp, x), -1, -1)) == "s")
                    {
                        amount = amount + ((integer)llGetSubString(llList2String(tmp, x), 0, -2) * 100);
                    }
                    else if(llToLower(llGetSubString(llList2String(tmp, x), -1, -1)) == "c")
                    {
                        amount = amount + ((integer)llGetSubString(llList2String(tmp, x), 0, -2) * 1);
                    }
                }while(x++<=i);
                dataSet += [(string)amount];
                llDialog(llGetOwner(), "Give to who?", ["Myself", "Other"], channel);
            }
        }
        // If we're doing money!
        else if(m == "Give Money")
        {
            function = "giveMoney";
            llTextBox(llGetOwner(), "How much? 1g = 1000C, 1s = 100C. You can input g, s, & c.", channel);
        }
        else if(m == "takeMoney")
        {
            
        }
        else if(m == "Money")
        {
            function = "money";
            llSetTimerEvent(time);
            llDialog(llGetOwner(), " ", orderButtons(moneyMenu), channel);
        }
        // LOGS FUNCTIONS
        else if(m == "Logs")
        {
            pageLogs = 1;
            llSetTimerEvent(time);
            llDialog(llGetOwner(), " ", orderButtons(logsMenu), channel);
        }
        else if(m == "Chk Logs")
        {
            llSetTimerEvent(time);
            function = "chkLogs";
            query = "func=chkLogs&page=" + (string)pageLogs;
            ping();
        }
        else if(m == "Srch Logs")
        {
            llSetTimerEvent(time);
            function = "srchLogs";
            llTextBox(llGetOwner(), "Search for logs by username, uuid, character name, or char id", channel);
        }
        // ITEM FUNCTIONS
        else if(m == "Items")
        {
            llSetTimerEvent(time);
            llDialog(llGetOwner(), " ", orderButtons(itemMenu), channel);
        }
        else if(m == "Give Item")
        {
            llSetTimerEvent(time);
            function = "giveItem";
            llTextBox(llGetOwner(), "Item ID for item you want to give", channel);
        }
    }
    
    timer()
    {
        clearAll();
    }
    
    link_message(integer link, integer value, string msg, key id)
    {
        if(msg == "openstaffmenu" && value == staffLinkMsgNum)
        {
            clearAll();
            listener = llListen(channel, "", llGetOwner(), "");
            llDialog(llGetOwner(), " ", orderButtons(mainMenu), channel);
            llSetTimerEvent(time);
        }
    }
    
    on_rez(integer start)
    {
        llResetScript();
    }
}
