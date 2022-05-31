string serverURL = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/inventory_trade_handler.php?";
key onlineCommsKey;
integer Key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
integer tradeNum = 90900;
integer persNum = 90800;
integer menuChan;
integer menuHandler;
integer trading = FALSE;
integer page = 1;
list itemData;
integer amount;
list targets;
list targetNames;
key tId;
float time = 30.0;
sendDataToServer(string data)
{
    onlineCommsKey = llHTTPRequest(serverURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "" + data);
}
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

list order_buttons(list buttons)
{
    return llList2List(buttons, -3, -1) + llList2List(buttons, -6, -4)
         + llList2List(buttons, -9, -7) + llList2List(buttons, -12, -10);
}

sendMessageToTarget(string message, key target)
{
    /* What the handler will be listening for.
         if(m == ("::readyUpdate::" + (string)llGetOwner()) && !rdyUpd)
            {
                rdyUpd = TRUE;
            }
            else if(m == ("::doUpdate::" + (string)llGetOwner()) && rdyUpd)
            {
                rdyUpd = FALSE;
                sendDataToServer("func=personal&input=updateInventory");
            }
    */
    integer tmp = Key2AppChan(target, 1338); // Target channel.
    llRegionSayTo(target, tmp, message);
}

list tradeMenu = ["Give Item",
"Give Money", "Cancel"
];

clearAll()
{
    llListenRemove(menuHandler);
    menuChan = FALSE;
    itemData = [];
    targets = [];
    trading = FALSE;
    targetNames = [];
    page = 1;
}

list parseTargets()
{
    integer x;
    if(page == 1)
    {
        x = 0;
    }
    else
    {
        x = (9 * page - 9);
    }
    list tmp = llList2List(targetNames, x, (x+8));
    if(llGetListLength(targetNames) > 9)
    {
        if(page > 1)
        {
            tmp += ["<<"];
        }
        tmp += ["Cancel"];
        if(llGetListLength(tmp) > 8)
        {
            tmp += [">>"];
        }
    }
    else
    {
        tmp += [" ", "Cancel", " "];
    }
    return order_buttons(tmp);
}

default
{
    state_entry()
    {
        // After init, inform the handler that script is ready.
        llMessageLinked(LINK_THIS, 1, llGetScriptName(), "");
    }
    
    http_response(key id, integer status, list meta, string body)
    {
        if(id != onlineCommsKey)
        {
            return;
        }
        if(status > 201 ^ status < 200)
        {
            llOwnerSay("Error: " + (string)status + "\n\n" + body);
            return;
        }
        if(llGetSubString(body, 0, 3) == "err:")
        {
            llOwnerSay(llGetSubString(body, 4, -1));
            clearAll();
            return;
        }
        //llOwnerSay(body);
        if(~llSubStringIndex(body, "tradesuccess::"))
        {
            list tmp = llParseString2List(body, ["::"], []);
            tId = (key)llList2String(tmp, 2);
            sendMessageToTarget("::readyUpdate::" + (string)tId, tId);
            llSleep(0.2);
            sendMessageToTarget("::doUpdate::" + (string)tId, tId);
            string out = llList2String(tmp, 5) + " has given " + llList2String(tmp, 1) + " " + llList2String(tmp, 4) + "x " + llList2String(tmp, 3) + ".";
            llSay(0, out);
            //llRegionSayTo((key)llList2String(tmp, 2), 0, out);
            //llOwnerSay("You have given " + llList2String(tmp, 4) + "x " + llList2String(tmp, 3) + " to " + llList2String(tmp, 1) + ".");
            llMessageLinked(LINK_THIS, tradeNum, "::updateinventory::", "");
            clearAll();
        }
        // "moneysuccess::{$this->charReceivingName}::{$this->settings['money_name']}::{$amount}::{$this->charGivingName}::{$this->charReceivingUuid}"
        else if(~llSubStringIndex(body, "moneysuccess::"))
        {
            // We've traded some money!
            list tmp = llParseString2List(body, ["::"], []);
            tId = (key)llList2String(tmp, -1);
            sendMessageToTarget("::readyUpdate::" + (string)tId, tId);
            llSleep(0.2);
            sendMessageToTarget("::doUpdate::" + (string)tId, tId);
            string out = llList2String(tmp, 4) + " has given " + llList2String(tmp, 1) + " " + llList2String(tmp, 3) + " " + llList2String(tmp, 2) + ".";
            llSay(0, out);
            //llRegionSayTo(tId, 0, out);
            //llOwnerSay("You have given " + llList2String(tmp, 3) + " " + llList2String(tmp, 2) + " to " + llList2String(tmp, 1) + ".");
            llMessageLinked(LINK_THIS, tradeNum, "::updateinventory::", "");
            clearAll();
        }
    }
    
    sensor(integer num)
    {
        integer x = 0;
        do
        {
            if(llDetectedKey(x) != llGetOwner())
            {
                targets += [(string)llDetectedKey(x)];
                string tmp = llDetectedName(x);
                if(llStringLength(tmp) > 12)
                {
                    tmp = llGetSubString(tmp, 0, 11);
                }
                targetNames += [tmp];
            }
            ++x;
        } while(x <= (num - 1));
        page = 1;
        llDialog(llGetOwner(), "Choose your recipient: ", parseTargets(), menuChan);
    }
    
    attach(key id)
    {
        if(llGetAttached())
        {
            llResetScript();
        }
    }
    
    on_rez(integer start)
    {
        clearAll();
    }
    
    timer()
    { // Time out the session after a while.
        llSetTimerEvent(0);
        clearAll();
    }
    
    link_message(integer link, integer num, string body, key id)
    {
        if(num == tradeNum)
        {
            if(body == "::starttransaction::")
            {
                // Code to start transaction here.
                // Open the menu & take over interactions for the session.
                clearAll();
                menuChan = Key2AppChan(llGetOwner(), 271291);
                menuHandler = llListen(menuChan, "", llGetOwner(), "");
                llDialog(llGetOwner(), "What do you want to do?", tradeMenu, menuChan);
                llSetTimerEvent(time);
            }
        }
        else if(num == (tradeNum + 1) && trading == -1)
        {
            if(~llSubStringIndex(body, "fullinv:"))
            {
                llOwnerSay(
                "You've been affected by a heisenbug that's currently being tracked. Please contact Tenaar Feiri with the following information:\n\nNum: "
                +
                (string)num + ", adjusted: " + (string)(num + 1) + ", expecting: " + (string)(tradeNum + 1)
                +
                "\n"
                +
                "Body: " + body
                +
                ", expected: item_name"
                );
                return;
            }
            itemData = llParseString2List(body, ["|"], []);
            llTextBox(llGetOwner(), "How much " + llList2String(itemData, 0) + " would you like to give away?", menuChan);
            trading = 1;
        }
    }
    
    listen(integer c, string n, key id, string m)
    {
        if(c != menuChan)
        {
            return;
        }
        if(m == llList2String(tradeMenu, 0))
        {
            // Whatever this is, we're giving an item.
            llMessageLinked(LINK_THIS, tradeNum, "giveitem", "");
            llDialog(llGetOwner(), "Choose which item you want to give away by touching its HUD icon.", ["OK"], menuChan);
            trading = -1;
        }
        else if(m == llList2String(tradeMenu, 1))
        {
            // This is giving money away.
            trading = 2;
            llTextBox(llGetOwner(), "How much are you giving away?", menuChan);
        }
        else
        {
            if(trading == 1)
            {
                // Giving item away.
                if(isInteger(m))
                {
                    amount = (integer)m;
                    trading = 3;
                    llSensor("", "", AGENT, 20, PI);
                }
                else
                {
                    llDialog(llGetOwner(), "Not a number.", ["OK"], menuChan);
                    clearAll();
                }
            }
            else if(trading == 2)
            {
                // Giving money away.
                if(isInteger(llStringTrim(m, STRING_TRIM)))
                {
                    // If it's an integer, do sensor!
                    amount = (integer)m;
                    trading = 4;
                    llSensor("", "", AGENT, 20, (PI*2));
                }
                else
                {
                    // Otherwise see if we're doing a list! Whitespace-separated.
                    list tmp = llParseString2List(llStringTrim(m, STRING_TRIM), [" "], []);
                    integer i = (llGetListLength(tmp) - 1);
                    integer x = 0;
                    string r;
                    amount = 0;
                    do
                    {
                        r = llGetSubString(llList2String(tmp, x), 0, -2);
                        if(!isInteger(r))
                        {
                            llDialog(llGetOwner(), "Can't give money; invalid amount entered. Your input: " + m, ["OK"], menuChan);
                            clearAll();
                            return;
                        }
                        if(llToLower(llGetSubString(llList2String(tmp, x), -1, -1)) == "g")
                        {
                            llDialog(llGetOwner(), "Can't give money; invalid amount entered. Your input: " + m, ["OK"], menuChan);
                            clearAll();
                            return;
                        }
                        else if(llToLower(llGetSubString(llList2String(tmp, x), -1, -1)) == "s")
                        {
                            llDialog(llGetOwner(), "Can't give money; invalid amount entered. Your input: " + m, ["OK"], menuChan);
                            clearAll();
                            return;;
                        }
                        else if(llToLower(llGetSubString(llList2String(tmp, x), -1, -1)) == "c")
                        {
                            llDialog(llGetOwner(), "Can't give money; invalid amount entered. Your input: " + m, ["OK"], menuChan);
                            clearAll();
                            return;
                        }
                    }while(x++<=i);
                    trading = 4;
                    llSensor("", "", AGENT, 20, PI);
                }
            }
            else if(trading == 3 ^ trading == 4)
            {
                // We're doing a thing!
                if(trading == 3)
                {
                    // Giving items.
                    string k = llList2String(targets, llListFindList(targetNames, [m]));
                    string id = llList2String(llParseString2List(llList2String(itemData, 1), [":"], []), 0);
                    string am = (string)amount;
                    sendDataToServer("target=" + k + "&id=" + id + "&amount=" + am);
                }
                else if(trading == 4)
                {
                    // Giving money.
                    string k = llList2String(targets, llListFindList(targetNames, [m]));
                    string am = (string)amount;
                    sendDataToServer("target=" + k + "&money&amount=" + am);
                }
            }
        }
    }
        
}