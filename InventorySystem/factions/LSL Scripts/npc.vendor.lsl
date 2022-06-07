/*
    Generic Vendor Script

*/
integer debug = FALSE;
string serverURL = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/factions/faction_handler.php?";
integer npcId;
integer npcFaction;
list npcData = ["", "", ""]; // blurb Name, dialog options, dialog commands
list itemNames;
list itemIds;
string chosenItemName;
integer chosenItemId;
integer viewGoodsPage = 1;
integer factionChk;
key user;
key npcVendorGeneral;
key npcVendorViewItem;
key npcVendorItemData;
key npcVendorMakeTransaction;
key ncRead;
string ncName = "settings";
integer ncLine = 0;
integer mode = 1; // 1 = Greeting; 2 = Browsing; 3 = Buying; 4 = Selling.
float timeout = 30.0;
integer menuChannel;
integer menuListener;
key ping(string data)
{
   return llHTTPRequest(serverURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "npcId=" + (string)npcId + "&" + data);
}
resetAll()
{
    // Reset everything!
    npcVendorGeneral = "";
    npcVendorViewItem = "";
    ncRead = "";
    ncLine = 0;
    mode = 1;
    user = "";
    viewGoodsPage = 1;
    npcData = ["", "", ""];
    itemNames = [];
    itemIds = [];
    chosenItemName = "";
    chosenItemId = 0;
    factionChk = FALSE;
    llSetTimerEvent(0);
}
updateNpcData(string data, integer pos)
{
    npcData = llListReplaceList(npcData, [(string)data], pos, pos);
}
integer Key2AppChan(key ID, integer App) {
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
list order_buttons(list buttons)
{
    return llList2List(buttons, -3, -1) + llList2List(buttons, -6, -4)
         + llList2List(buttons, -9, -7) + llList2List(buttons, -12, -10);
}
//if (isNumber(string) & 1) to test if it's an integer of either sign.
//if (isNumber(string) & 2) to test if it's a float of either sign.
//if (isNumber(string) & 4) to test if it's either a float or an integer of either sign.
//if (isNumber(string) & 8) to test for a positive integer.
//if (isNumber(string) & 16) to test for a negative integer.
//if (isNumber(string) & 32) to test for a positive float.
//if (isNumber(string) & 64) to test for a negative float.
//if (isNumber(string) & 128) to test for a positve integer or float.
//if (isNumber(string) & 256) to test for a negative integer or float.
integer isNumber(string input)
{
    integer i = 0;
    integer c = 0;
    integer l = llStringLength(input);
    string s;
    while (c < l) {
        s = llGetSubString(input,c,c);
        if ( ( (string)((integer)s) == s ) || ( s == "-" ) || ( s == "." ) ) {
            
            //Test for possible negative integers.
            if (s == "-") {
                if (~i & 4) i = 4|i; //If it's not already tagged, flip the 3rd bit if it's a negative sign.
                else { i = 0; return i; } //If we come across more than one negative in the string fail.
            }
            
            else if (s == ".") {
                if (~i & 2) i = 2|i; //If it's not already tagged, flip the 2nd bit if it's a period.
                else { i = 0; return i; } //If we come across more than one period in the string fail.
            }
            
            else i = 1|i; //Flip the first bit if it otherwise passed the ((string)((integer)string) == string) test.  
                          //AFAIK, thats just 0 - 9 in lsl.
                
            ++c;
        } else {
            return 0;
        }
    }
    
    //if there's a minus sign in here somewhere, make sure it's the first character in the string.
    if ( (i & 4) && (llGetSubString(input,0,0) != "-") ) { i = 0; return i; }
    
    //Now take our three bits and expand them so the user can have lots of test switches avilable.
    if (i == 1)      i = 141; //010001101  A positive integer.
    else if (i == 5) i = 277; //100010101  A negative integer.
    else if (i == 3) i = 166; //010100110  A positive float.
    else if (i == 7) i = 326; //101000110  A negative float.
    else if (i == (2|4|6)) i = 0;  //just got periods and/or negative signs but no numbers....so //000

    return i;
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
default
{
    state_entry()
    {
        // Setup code here.
        if(llGetInventoryType(ncName) == INVENTORY_NONE)
        {
            llWhisper(0, "No notecard named \"" + ncName + "\" detected.");
            return;
        }
        ncRead = llGetNotecardLine(ncName, ncLine++);
    }
    touch_end(integer touched)
    {
        if(!llAgentInExperience(llDetectedKey(0)))
        {
            llRegionSayTo(llDetectedKey(0), 0, "((You're not in the sim experience & cannot use this NPC. To join, find a RP tool redelivery dispenser somewhere and touch it & accept the requested experience, or accept the experience when teleporting into the sim 15 seconds or more after jumping out.))");
            return;
        }
        if(user != "" && llDetectedKey(0) != user)
        {
            llDialog(llDetectedKey(0), "Sorry; I'm busy with someone else right now. Please wait a moment!", ["OK"], -1);
            return;
        }
        else if(user == "" || llDetectedKey(0) == user)
        {
            resetAll();
        }
        llSetTimerEvent(timeout);
        user = llDetectedKey(0);
        llSensor("", user, AGENT, 5.0, PI);
    }
    changed(integer change)
    {
        if(change & CHANGED_OWNER)
        {
            llResetScript();
        }
        else if(change & CHANGED_REGION_START)
        {
            llResetScript();
        }
        else if(change & CHANGED_INVENTORY)
        {
            llWhisper(0, "Inventory change detected. Resetting...");
            llResetScript();
        }
    }
    sensor(integer detected)
    {
        menuChannel = Key2AppChan(user, (integer)llFrand(100 + llFrand(100)));
        menuListener = llListen(menuChannel, "", user, "");
        llSetTimerEvent(timeout);
        npcVendorGeneral = ping("usr="+(string)user+"&npc&func=npcVendor&action=showBlurb,0");
    }
    no_sensor()
    {
        resetAll();
    }
    timer()
    {
        resetAll();
    }
    listen(integer c, string n, key id, string m)
    {
        if(c != menuChannel)
        {
            return;
        }
        if(mode == 1) // Initiating chat/doing blurbs.
        {
            list choices = llParseString2List(llList2String(npcData, 1), [":"], []);
            list commands = llParseString2List(llList2String(npcData, 2), [":"], []);
            integer pos = llListFindList(choices, [m]);
            if(pos != -1)
            {
                string cmd = llList2String(commands, pos);
                if(cmd == "exit")
                {
                    //llDialog(user, "Goodbye!", ["OK"], -1);
                    resetAll();
                    return;
                }
                if(cmd == "viewGoods" || ~llSubStringIndex(cmd, "viewGoods,"))
                {
                    llSetTimerEvent(timeout);
                    mode = 2;
                    if(~llSubStringIndex(cmd, "viewGoods,"))
                    {
                        list tmp = llParseString2List(cmd, [","], []);
                        factionChk = TRUE;
                        npcVendorViewItem = ping("usr="+(string)user+"&npc&func=npcVendor&action=viewGoods," + (string)viewGoodsPage + "," + llList2String(tmp, 1));
                    }
                    else
                    {
                        npcVendorViewItem = ping("usr="+(string)user+"&npc&func=npcVendor&action=viewGoods," + (string)viewGoodsPage);
                    }
                }
                else
                {
                    llSetTimerEvent(timeout);
                    npcVendorGeneral = ping("usr="+(string)user+"&npc&func=npcVendor&action="+cmd);
                }
            }
            else
            {
                llRegionSayTo(user, 0, "((An error has occurred. Choice param returned -1. Please contact staff.");
                resetAll();
                return;
            }
        }
        else if(mode == 2) // Browsing!
        {
            if(m == "<<")
            {
                if((viewGoodsPage - 1) > 0)
                {
                    --viewGoodsPage;
                    llSetTimerEvent(timeout);
                    npcVendorViewItem = ping("usr="+(string)user+"&npc&func=npcVendor&action=viewGoods," + (string)viewGoodsPage);
                }
                else
                {
                    llSetTimerEvent(timeout);
                    npcVendorViewItem = ping("usr="+(string)user+"&npc&func=npcVendor&action=viewGoods," + (string)viewGoodsPage);
                }
            }
            else if(m == ">>")
            {
                ++viewGoodsPage;
                llSetTimerEvent(timeout);
                npcVendorViewItem = ping("usr="+(string)user+"&npc&func=npcVendor&action=viewGoods," + (string)viewGoodsPage);
            }
            else if(m == "Goodbye")
            {
                llDialog(user, "Goodbye!", ["OK"], -1);
                resetAll();
                return;
            }
            else if(m == "Buy")
            {
                mode = 3;
                llSetTimerEvent(timeout);
                llTextBox(user, "How many would you like to buy? Type 'cancel' without quotations to cancel transaction.", menuChannel);
            }
            else if(m == "Sell")
            {
                mode = 4;
                llSetTimerEvent(timeout);
                llTextBox(user, "How many would you like to sell? Type 'cancel' without quotations to cancel transaction.", menuChannel);
            }
            else
            {
                integer pos = llListFindList(itemNames, [(string)m]);
                if(pos == -1)
                {
                    llRegionSayTo(user, 0, "((An error has occurred. Choice param returned -1. Please contact staff.))");
                    resetAll();
                    return;
                }
                chosenItemName = llList2String(itemNames, pos);
                chosenItemId = (integer)llList2String(itemIds, pos);
                npcVendorItemData = ping("usr="+(string)user+"&npc&func=npcVendor&action=viewItem," + (string)chosenItemId);
                llSetTimerEvent(timeout);
            }
        }
        else if(mode == 3) // Buying
        {
            if(llToLower(m) == "cancel")
            {
                llRegionSayTo(user, 0, "Canceled!");
                resetAll();
                return;
            }
            if(isNumber(m) & 8)
            {
                integer amount = (integer)m;
                npcVendorMakeTransaction = ping("usr="+(string)user+"&npc&func=npcVendor&action=buyItem," + (string)chosenItemId + "," + (string)amount);
            }
        }
        else if(mode == 4) // Selling!
        {
            
            if(llToLower(m) == "cancel")
            {
                llRegionSayTo(user, 0, "Canceled!");
                resetAll();
                return;
            }
            if(isNumber(m) & 8)
            {
                integer amount = (integer)m;
                npcVendorMakeTransaction = ping("usr="+(string)user+"&npc&func=npcVendor&action=sellItem," + (string)chosenItemId + "," + (string)amount);
            }
        }
    }
    http_response(key request_id, integer status, list metadata, string body)
    {
        if(status > 201 || status < 200)
        {
            llRegionSayTo(user, 0, "((Error " + (string)status + "! Unable to fulfill request. Error msg:\n\n" + body + "))");
            resetAll();
            return;
        }
        else if(~llSubStringIndex(body, "err:") && request_id != npcVendorMakeTransaction && !factionChk)
        {
            llRegionSayTo(user, 0, "((Server error! \n" + llGetSubString(body, (llSubStringIndex(body, ":") + 1), -1) + "))");
            resetAll();
            return;
        }
        else if(factionChk)
        {
            llRegionSayTo(user, 0, llGetSubString(body, (llSubStringIndex(body, ":") + 1), -1));
            llDialog(user, "Choose an option", ["Oh, okay..."], -1);
            resetAll();
            return;
        }
        if(debug)
        {
            llOwnerSay(body);
        }
        if(request_id == npcVendorGeneral)
        {
            list tmp = llParseString2List(body, ["&&"], []);
            updateNpcData(llList2String(tmp, 0), 0);
            llSetObjectName(llList2String(npcData, 0));
            updateNpcData(llList2String(tmp, 2), 1); // Add dialogue options.
            updateNpcData(llList2String(tmp, 3), 2); // Dialogue commands.
            string emote = llList2String(tmp, 4);
            if(emote == "")
            {
                emote = " ";
            }
            llRegionSayTo(user, 0, llList2String(tmp, 1));
            llDialog(user, emote, order_buttons(llParseString2List(llList2String(npcData, 1), [":"], [])), menuChannel);
            llSetTimerEvent(timeout);
        }
        else if(request_id == npcVendorViewItem)
        {
            // 1,2,5,19,20,10,3,12,16&&Iron Ore,Iron Ingot,Iron Casing,Birch Wood,Charcoal,Tin Ore,Silver Ore,Iron Sword,Iron Armour
            itemNames = llParseString2List(llList2String(llParseString2List(body, ["&&"], []), 1), [","], []);
            itemIds = llParseString2List(llList2String(llParseString2List(body, ["&&"], []), 0), [","], []);
            llDialog(user, "What would you like to buy?", order_buttons(itemNames + ["<<", "Goodbye", ">>"]), menuChannel);
            llSetTimerEvent(timeout);
        }
        else if(request_id == npcVendorItemData)
        {
            list choices = [];
            if(!~llSubStringIndex(body, "Not for sale."))
            {
                choices += ["Buy"];
            }
            if(!~llSubStringIndex(body, "Won't buy."))
            {
                choices += ["Sell"];
            }
            choices += ["Goodbye"];
            llDialog(user, body, order_buttons(choices), menuChannel);
        }
        else if(request_id == npcVendorMakeTransaction)
        {
            if(~llSubStringIndex(body, "err:"))
            {
                llDialog(user, llGetSubString(body, (llSubStringIndex(body, ":") + 1), -1), ["Oh, okay..."], -1);
                resetAll();
                return;
            }
            list tmp = llParseString2List(body, ["&&"], []);
            if(llList2String(tmp, 1) == "success")
            {
                if(llList2String(tmp, 0) == "purchase")
                {
                    llSetObjectName("");
                    llRegionSayTo(user, 0, "You have bought " + llList2String(tmp, 2) + "x " + llList2String(tmp, 3) + " from "+llList2String(npcData, 0)+".");
                    llSetObjectName(llList2String(npcData, 0));
                }
                else if(llList2String(tmp, 0) == "sale")
                { //"sale&&success&&" . $this->npcActionArr[2] . "&&" . $item['name'] . "&&" . $total;
                    llSetObjectName("");
                    llRegionSayTo(user, 0, "You have sold " + llList2String(tmp, 2) + "x " + llList2String(tmp, 3) + " to "+llList2String(npcData, 0)+", for " + llList2String(tmp, 4) + " Crowns.");
                    llSetObjectName(llList2String(npcData, 0));
                }
                sendMessageToTarget("::readyUpdate::" + (string)user, user);
                llSleep(0.2);
                sendMessageToTarget("::doUpdate::" + (string)user, user);
                mode = 2;
                llSetTimerEvent(timeout);
                llDialog(user, "What would you like to buy?", order_buttons(itemNames + ["<<", "Goodbye", ">>"]), menuChannel);
            }
        }

    }
    dataserver(key id, string data)
    {
        if(id == ncRead)
        {
            if(data == EOF)
            {
                if(npcId && (integer)timeout)
                {
                    llWhisper(0, "NPC notecard read and processed.");
                }
                else
                {
                    llWhisper(0, "An error has occurred; Npc id, Npc faction or Npc timeout is lacking configuration.");
                }
            }
            else
            {
                // Set up code!
                string tmp = llStringTrim(llGetSubString(data, 0, (llSubStringIndex(data, "#"))), STRING_TRIM); // Get data, ignore comments.
                if(tmp == "")
                {
                    ncRead = llGetNotecardLine(ncName, ncLine++);
                }
                else
                {
                    list arr = llParseString2List(tmp, [":"], []);
                    tmp = llList2String(arr, 0);
                    if(tmp == "Npc id")
                    {
                        npcId = (integer)llStringTrim(llList2String(arr, 1), STRING_TRIM);
                    }
                    else if(tmp == "Npc faction")
                    {
                        npcFaction = (integer)llStringTrim(llList2String(arr, 1), STRING_TRIM);
                    }
                    else if(tmp == "Npc timeout")
                    {
                        timeout = (float)llStringTrim(llList2String(arr, 1), STRING_TRIM);
                    }
                    ncRead = llGetNotecardLine(ncName, ncLine++);
                }
            }
        }
    }
}