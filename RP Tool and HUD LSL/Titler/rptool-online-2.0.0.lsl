key uuid;
string version = "2.0.0";

integer commandChannel = 1;
integer hud_channel;
integer faction_channel;
integer debug = FALSE;
integer loaded = FALSE; 
integer charid = 0;
string charname;
integer manualSave = FALSE;
integer silentRolling = FALSE;
integer deleteMe = FALSE;
integer resetMe = FALSE;
integer resetChar = FALSE;
integer autosave = TRUE;
integer stfu = FALSE; 

integer charpage = 1;

string serverURL = "https://neckbeardsanon.xen.prgmr.com/rptool/lismore/rptool-main.php?";
string diceUrl = "https://neckbeardsanon.xen.prgmr.com/rptool/lismore/diceroll.php?";
string invUrl =  "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/inventory_handler.php?";
string factionURL = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/factions/faction_handler.php?";

list charList;

key onlineCommsKey;
key factionKey;

integer settingsChan = 1; 
integer settingsHandler; 
list forbiddenChannels = ["3", "4", "22"];

integer loadChan;
integer loadChanHandler;

float timerLimit = 10.0;



key dicerollKey;
string greeting;

list order_buttons(list buttons)
{
    return llList2List(buttons, -3, -1) + llList2List(buttons, -6, -4)
         + llList2List(buttons, -9, -7) + llList2List(buttons, -12, -10);
}


string stripTags(string data)
{
    
    
    
    while(~llSubStringIndex(data, "$"))
    {
        
        

        
        integer tagBeginning = llSubStringIndex(data, "$");

        
        data = llDeleteSubString(data, tagBeginning, (tagBeginning + 1));

        
    }
    
    while(~llSubStringIndex(data, "@invis@"))
    {
        integer inx = llSubStringIndex(data, "@invis@");
        data = llStringTrim(llDeleteSubString(data, inx, (inx+6)), STRING_TRIM);
    }
    
    return llStringTrim(data, STRING_TRIM);

}



sendDataToServer(string data)
{
    onlineCommsKey = llHTTPRequest(serverURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "version="+ (string)version + data);
    if(manualSave)
    {
        llOwnerSay("Character \"" + stripTags(charname) +"\" has been attempted manually saved to server. Under normal circumstances, the RP tool autosaves 10 seconds after last change. If this backup solution failed, please contact Tenaar Feiri by notecard.");
        manualSave = FALSE;
    }
}

parseReturnValue(string body)
{
    if(~llSubStringIndex(body, "alltitles:"))
    {
        llMessageLinked(LINK_THIS, 14, body, NULL_KEY);
        llListenRemove(loadChanHandler);
    }
    else if(~llSubStringIndex(body, "charlist:"))
    {
        integer num = llSubStringIndex(body, "charlist:");
        integer count = llStringLength("charlist:");
        body = llStringTrim(llDeleteSubString(body, num, (count - 1)), STRING_TRIM);
        charList = llParseString2List(body, ["=>"], []);
        integer x;
        integer y = (llGetListLength(charList) - 1);
        string temp;
        string filtering;
        list menu;
        do
        {
            if(llList2String(charList, x) != "EOF")
            {
                filtering = llList2String(charList, x);
                integer colon = llSubStringIndex(filtering, ":");
                filtering = llDeleteSubString(filtering, 0, colon);
                temp += (string)(x+1) + " => " + filtering + "\n";
                menu += [(string)(x+1)];
            }
            ++x;
        } while (x <= y);
        menu += ["<--", "CANCEL", "-->"];
        string tooltip = "Select character (page " + (string)charpage + "):\n" + temp;
        llDialog(llGetOwner(), stripTags(tooltip), order_buttons(menu), loadChan);
    }
    
}
integer key2AppChan(key ID, integer App) { 
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}

integer isInteger(string data)
{
    if ( (string) ( (integer) data) == data)
    {
        return TRUE;
    }
    return FALSE;
}

fullReset()
{
    integer inventoryNumber = llGetInventoryNumber(INVENTORY_ALL);
 
        integer index;
        for ( ; index < inventoryNumber; ++index )
        {
            string itemName = llGetInventoryName(INVENTORY_ALL, index);
            if (itemName != llGetScriptName() && llGetInventoryType(itemName) == INVENTORY_SCRIPT)
            {
                if(itemName != "rptool attacher")
                {
                    llResetOtherScript(itemName);
                }
            }
        }
}

integer Key2AppChan(key ID, integer App) {
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
getLastLoaded()
{
    lastLoaded = llHTTPRequest(invUrl, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "func=getlastid&charId=0");
}
key lastLoaded;
default
{
    state_entry()
    {
        if(!llGetAttached())
        {
            // Do nothing if we're not attached.
            return;
        }
        hud_channel = Key2AppChan(llGetOwner(), 1337);
        faction_channel = Key2AppChan(llGetOwner(), 1338);
        llListen(hud_channel, "", "", "");
        fullReset();
        llSetText("", <0,0,0>, 0);
        settingsHandler = llListen(settingsChan, "", "", "");
        sendDataToServer("&checkversion=1");
        uuid = llGetOwner();
        loaded = TRUE;
        getLastLoaded();
    }
    

    listen(integer chan, string name, key id, string message)
    {
        if(!llGetAttached())
        {
            // Do nothing if we're not attached.
            return;
        }
        if(chan == faction_channel)
        {
            list tmp = llParseString2List(message, ["::"],[]);
            if(llList2String(tmp, 0) == "factionupdate" && llList2String(tmp, 1) == (string)llGetOwner()) // Update faction when someone's HUD informs the RP tool that their faction status has changed.
            {
                factionKey = llHTTPRequest(factionURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "status&func=whoAmI&usr=" + (string)llGetOwner());
            }
        }
        else if(llGetOwner() == id || (llGetOwnerKey(id) == llGetOwner() && llList2String(llGetObjectDetails(id, [OBJECT_DESC]), 0) == "rptool-hud"))
        {
            if(chan == loadChan)
            {
                
                
                if(llToLower(message) == "-->")
                {
                    charpage = (charpage + 1);
                    sendDataToServer("&charlist="+(string)charpage);
                }
                else if(llToLower(message) == "<--")
                {
                    if(charpage > 1)
                    {                       
                        charpage = (charpage - 1);
                    }
                    
                    sendDataToServer("&charlist="+(string)charpage);
                }
                else if(llToLower(message) == "cancel")
                {
                    llOwnerSay("Canceled.");
                    llListenRemove(loadChanHandler);
                }
                else if(isInteger(message))
                {
                    
                    if(!debug)
                    {
                        string char = llList2String(charList, ((integer)message - 1));
                        string character_id = llGetSubString(char, 0, (llSubStringIndex(char, ":") - 1));
                        if(isInteger(character_id))
                        {
                            sendDataToServer("&loadchar=" + character_id);
                        }
                    }
                    else {
                        llOwnerSay(message);
                    }
                }
            }
            else if(chan == settingsChan || chan == hud_channel)
            {
                if(llGetSubString(llToLower(message), 0, 3) == "roll" || llGetSubString(llToLower(message), 0, 9) == "silentroll")
                {
                    
                    list rolltmp = llParseString2List(message, [" "], []);
                    if(~llSubStringIndex(llList2String(rolltmp, 0), "silentroll"))
                    {
                        silentRolling = TRUE;
                    }
                    else
                    {
                        silentRolling = FALSE;
                    }
                    if(llGetListLength(rolltmp) >= 4)
                    {
                        string num = llList2String(rolltmp, 1);
                        string max;
                        string min = llList2String(rolltmp, 2);
                        if(!isInteger(num))
                        {
                            num = "1";
                        }
                        if(!isInteger(min))
                        {
                            min = "1";
                        }

                        max = llList2String(rolltmp, 3);
                        if(!isInteger(max))
                        {
                            max = "20";
                        }
                        dicerollKey = llHTTPRequest(diceUrl, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], 
                        "num="+num+"&min="+min+"&max="+max);
                    }
                    else if(llGetListLength(rolltmp) == 3)
                    {
                        string num = llList2String(rolltmp, 1);
                        string max;
                        string min = "1";
                        if(!isInteger(num))
                        {
                            num = "1";
                        }

                        max = llList2String(rolltmp, 2);
                        if(!isInteger(max))
                        {
                            max = "20";
                        }
                        dicerollKey = llHTTPRequest(diceUrl, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], 
                        "num="+num+"&min="+min+"&max="+max);
                    }
                    else if(llGetListLength(rolltmp) == 2)
                    {
                        string max = llList2String(rolltmp, 1);
                        if(!isInteger(max))
                        {
                            max = "20";
                        }
                        dicerollKey = llHTTPRequest(diceUrl, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], 
                        "&min=1&max="+max);
                    }
                    else
                    {
                        dicerollKey = llHTTPRequest(diceUrl, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384,HTTP_VERIFY_CERT, FALSE], 
                        "&min=1&max=20");
                    }
                }
                else if(charid == 0 && llToLower(message) != "load" && llToLower(message) != "newchar")
                {
                    llOwnerSay("Please load or create a new character.");
                    return;
                }
                if(llGetSubString(llToLower(message), 0, 6) == "channel") 
                {
                    message = llStringTrim(llDeleteSubString(message, 0, 6), STRING_TRIM);
                    if(isInteger(message))
                    {
                        if(llListFindList(forbiddenChannels, [message]) != -1)
                        {
                            llOwnerSay("ERROR: Channel " + message + " is restricted. List of restricted channels: " + llList2CSV(forbiddenChannels));
                            return;
                        }
                        settingsChan = (integer)message;
                        llListenRemove(settingsHandler);
                        settingsHandler = llListen(settingsChan, "", "", "");
                        llMessageLinked(LINK_THIS, 15, (string)settingsChan, "");
                        
                        llOwnerSay("Channel updated successfully. All non-chatter commands must now be sent to channel: " + (string)settingsChan);
                    }
                    else
                    {
                        llOwnerSay("ERROR: Channel not a number.");
                    }
                }
                else if(llToLower(message) == "newchar")
                {
                    sendDataToServer("&create=1");
                }
                else if(llToLower(message) == "load")
                {
                    charpage = 1;
                    loadChan = key2AppChan(llGetKey(), (integer)llFrand(200.0));
                    loadChanHandler = llListen(loadChan, "", llGetOwner(), "");
                    sendDataToServer("&charlist=" + (string)charpage);
                }
                else if(llToLower(message) == "version")
                {
                    llOwnerSay("Neckbeards Anonymous RP Tool ONLINE version: " + (string)version);
                }
                else if(llToLower(message) == "reset")
                {
                    if(!resetMe)
                    {
                        llOwnerSay("Please click the reset button or type in the reset command again to confirm.");
                        resetMe = TRUE;
                        llSetTimerEvent(10);
                        llSleep(2.0);
                        return;
                    }
                    llOwnerSay("Resetting RP tool.");
                    fullReset();
                    llResetScript();
                }
                else if(message == "resetcharacter")
                {
                    if(resetChar)
                    {
                        resetChar = FALSE;
                        llOwnerSay("Resetting character \"" + charname + "\"");
                        sendDataToServer("&reset=1&charID="+(string)charid);
                    }
                    else
                    {
                        llOwnerSay("WARNING: THIS WILL RESET YOUR CURRENTLY ACTIVE CHARACTER AND ALL YOUR DATA WILL BE PERMANENTLY LOST!\nWARNING: THIS WILL RESET YOUR CURRENTLY ACTIVE CHARACTER AND ALL YOUR DATA WILL BE PERMANENTLY LOST!\nWARNING: THIS WILL RESET YOUR CURRENTLY ACTIVE CHARACTER AND ALL YOUR DATA WILL BE PERMANENTLY LOST!");
                        llSleep(3);
                        llOwnerSay("To proceed with character reset, click the HUD button or input the command again. Request will be reset in 5 seconds.");
                        resetChar = TRUE;
llSetTimerEvent(5);
                    }
                }
                else if(llToLower(message) == "autosave")
                {
                    if(autosave)
                    {
                        autosave = FALSE;
                        llOwnerSay("Autosave has been disabled; your characters are no longer automatically saved.");
                    }
                    else
                    {
                        autosave = TRUE;
                        llOwnerSay("Autosave has been enabled; your characters will now be saved automatically.");
                    }
                }
                else if(llToLower(message) == "save")
                {
                    if(charid) {
                        
                        manualSave = TRUE;
                        llSetTimerEvent(0.0);
                        llMessageLinked(LINK_SET, 13, "update", NULL_KEY);
                    }
                }
                else if(llToLower(message) == "support")
                {
                    llGiveInventory(llGetOwner(), "RP Tool Online Support Card");
                }
                else if(llToLower(message) == "help")
                {
                    llGiveInventory(llGetOwner(), "RP Tool Online Instructions");
                }
                else if(llToLower(message) == "togglegreeting")
                {
                    if(!stfu) {
                        stfu = TRUE;
                        llOwnerSay("RP tool will no longer greet you on rez/login.");
                    } else {
                        stfu = FALSE;
                        llOwnerSay("RP tool will now greet you on rez/login.");
                    }
                }
                else if(message == "deletecharacter")
                {
                    if(deleteMe == 2)
                    {
                        llSetTimerEvent(0.0);
                        deleteMe = 0;
                        
                        sendDataToServer("&delete="+(string)charid);
                        
                        fullReset();
                        llResetScript();
                    }
                    else {
                        deleteMe = 1;
                        llOwnerSay("You have requested to delete your currently loaded character: " + charname + ". Please wait 10 seconds, then input the command again to confirm.\nRequest will be canceled in 20 seconds.");
                        llSetTimerEvent(10);
                    }
                }
            }
        }
    }
    
    link_message(integer sender, integer num, string data, key id)
    {
        if(num == 12) 
        {
            if(data == "timer" && !manualSave)
            {
                llSetTimerEvent(timerLimit);
            }
            else if(~llSubStringIndex(data, "charid:"))
            {
                list tmp = llParseString2List(data, [":"], []);
                
                charid = llList2Integer(tmp, 1);
                charname = llList2String(tmp, 3);
            }
        }
        else if(num == 13) 
        {
            if(~llSubStringIndex(data, "&updall=1"))
            {
                sendDataToServer(data);
            }
        }
    }

    timer()
    {
        llSetTimerEvent(0.0);
        if(charid && autosave && !deleteMe && !resetMe) {
            
            
            llMessageLinked(LINK_SET, 13, "update", NULL_KEY);
        }
        else if(deleteMe)
        {
            if(deleteMe == 1)
            {
                llOwnerSay("You may now confirm character deletion by issuing command again. Request will be canceled in 10 seconds.");
                deleteMe = 2;
                llSetTimerEvent(timerLimit);
            }
            else if(deleteMe == 2)
            {
                deleteMe = FALSE;
                llOwnerSay("Deletion request timed out.");
            }
        }
        if(resetMe)
        {
            llOwnerSay("Reset window timed out.");
            resetMe = FALSE;
        }
        if(resetChar)
        {
            llOwnerSay("Character reset timed out.");
            resetChar = FALSE;
        }
        
    }

    changed(integer change)
    {
        if(change & CHANGED_OWNER)
        {
            if(llGetAttached())
            {
                fullReset();
                llResetScript();
            }
        }
    }
    
    attach(key agent)
    {
        if(llGetAttached())
        {
            fullReset();
            llResetScript();
        }
    }

    on_rez(integer start_param)
    {
        llSetText("", <1,1,1>, -1);
        if(!llGetAttached())
        {
            return;
        }
        else if(loaded && (llGetOwner() == uuid))
        {
            sendDataToServer("&checkversion=1");
            /*if(!stfu) {
                llOwnerSay(greeting);
            }*/
        }
        if(!autosave)
        {
            llOwnerSay("You currently have autosaving disabled. Re-enable with /" + (string)commandChannel + " autosave");
        }
        
    }

    http_response(key id, integer status, list meta, string body)
    {
        if(id == NULL_KEY)
        {
            llOwnerSay("Too many HTTP Requests; You have been throttled by the sim. Please wait a while to send a new request to the server.");
        }
        if(id == lastLoaded)
        {
            //llOwnerSay("Loading last used character.");
            if(body != "0" && body != "nochar")
            {
                sendDataToServer("&loadchar=" + body);
            }
            else if(body == "nochar")
            {
                fullReset();
                llSay(Key2AppChan(llGetOwner(), 1338), "deleted");
                charid = 0;
                llOwnerSay("Character deletion successful. Please load/create another.");
            }
            else
            {
                sendDataToServer("&create=1");
            }
            //llOwnerSay(body);
        }
        if(id == onlineCommsKey)
        {
            if((status != 200 && status != 201) && status != 499)
            {
                
                llOwnerSay("Server error. Returned code: " + (string)status);
                return;
            }
            else if(status == 499)
            {
                serverURL = "http://neckbeardsanon.xen.prgmr.com/rptool/dev/rptool-main.php?";
                diceUrl = "http://neckbeardsanon.xen.prgmr.com/rptool/dev/diceroll.php?";
                autosave = FALSE;
                llOwnerSay("Secure connection couldn't be established; falling back to insecure. Please try your command again.");
                llOwnerSay("Reset your RP tool to try and re-establish secure connection. Insecure connection will not jeopardize your personal information, though your character details may be susceptible to interception. Autosave has been disabled to protect your character details. Re-enable autosave manually with: /1 autosave");
            }
            if(~llSubStringIndex(body, "plzupdate:"))
            {
                string temporary;
                integer end = llSubStringIndex(body, ":");
                temporary = llStringTrim(llDeleteSubString(body, 0, end), STRING_TRIM);
                llOwnerSay(temporary);
            }
            parseReturnValue(body);
            // Then get faction information, if it exists.
            factionKey = llHTTPRequest(factionURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "status&func=whoAmI&usr=" + (string)llGetOwner());
        }
        else if(id == factionKey)
        {
            llMessageLinked(LINK_THIS, 16, body, "");
        }
        else if(id == dicerollKey)
        {
            if((status != 200 && status != 201) && status != 499)
            {
                llOwnerSay("Server error: " + (string)status + "\n"+body);
                return;
            }
            else if(status == 499)
            {
                serverURL = "http://neckbeardsanon.xen.prgmr.com/rptool/lismore/rptool-main.php?";
                diceUrl = "http://neckbeardsanon.xen.prgmr.com/rptool/lismore/diceroll.php?";
                autosave = FALSE;
                llOwnerSay("Secure connection couldn't be established; falling back to insecure. Please try your command again.");
                llOwnerSay("Reset your RP tool to try and re-establish secure connection. Insecure connection will not jeopardize your personal information, though your character details may be susceptible to interception. Autosave has been disabled to protect your character details. Re-enable autosave manually with: /" + (string)commandChannel + " autosave");
llOwnerSay(body);
            }
            
            string objname = llGetObjectName();
            if(silentRolling)
            {
                llSetObjectName(llKey2Name(llGetOwner()) + "'s silent dice roll");
                llOwnerSay(body);
            }
            else
            {
                llSetObjectName(llKey2Name(llGetOwner()) + "'s dice roll");
                llSay(0, body);
            }
            llSetObjectName(objname);
            
        }
    }

}