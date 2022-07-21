integer debug = FALSE;
string serverURL = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/crafting/crafting_station_handler.php?";
integer stationJobId = 1; // Blacksmith. The default.
string stationTitle = "Crafting station";
string stationDesc = "A crafting station wherein wondrous things can happen, once it is completed...";
string jobName;
string charName;
key onlineCommsKey;
integer recipeBrowsing = FALSE;
integer chosenRecipe = -2;
integer action;
key dsKey;
string notecard = "settings";
integer line = 0;
integer page = 1; // Which page we are at.
integer channel;
integer listener;
integer active = FALSE;
key usr;
float time = 30.0;
list menu = [
    "Craft",
    "Check stats",
    "Unregister"
];
list recipes;
list recipeFiltered; // For use with dialog buttons.
integer Key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
func_SendDataToServer(string data)
{
    onlineCommsKey = llHTTPRequest(serverURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "class=" + (string)stationJobId + "&" + data);
}
func_SetUpStation()
{
    /*
    */
    llOwnerSay("Setting up station!");
    channel = (integer)("0xF" + llGetSubString(llGetKey(),0,6)) + 10;
}
func_Timeout(integer status)
{
    llListenRemove(listener);
    if(status)
    {
        llRegionSayTo(usr, 0, "Timed out.");
    }
    usr = "";
    recipes = [];
    recipeFiltered = [];
    page = 1;
    active = FALSE;
    charName = "";
    action = FALSE;
    recipeBrowsing = FALSE;
    chosenRecipe = -2;
    llSetTimerEvent(0);
}
list func_OrderButtons(list buttons)
{
    if(llGetListLength(buttons) > 8)
    {
        buttons = llList2List(buttons, 0, 8);
        buttons += ["<<", "Cancel", ">>"];
    }
    else
    {
        buttons += [" ", "Cancel", " "];
    }
    return llList2List(buttons, -3, -1) + llList2List(buttons, -6, -4) +
        llList2List(buttons, -9, -7) + llList2List(buttons, -12, -10);
}
func_ParseRecipes(string data)
{
    llSetTimerEvent(time); // Reset the timer.
    recipes = [];
    recipeFiltered = [];
    recipes = llParseString2List(data, ["><"], []); // Parse the main string to recipes.
    integer i = (llGetListLength(recipes) - 1);
    integer x;
    do
    {
        string n = llStringTrim(llList2String(llParseString2List(llList2String(recipes, x), ["=>"], []), 1), STRING_TRIM);
        if(llStringLength(n) > 12)
        {
            n = llDeleteSubString(n, 12, -1);
        }
        recipeFiltered += [n];
        ++x;
    }while(x<=i);
    llDialog(usr, "Select which recipe to craft:", func_OrderButtons(recipeFiltered), channel);
}
integer func_IsInteger(string var)
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
default
{
    state_entry()
    {
        // Set up the new crafting stations.
        func_SetUpStation();
        if(llGetInventoryType(notecard) == INVENTORY_NONE)
        {
            llOwnerSay("Cannot find a notecard named: \"" + notecard + "\" in the object inventory.");
            return;
        }
        dsKey = llGetNotecardLine(notecard, line++);
    }
    
    on_rez(integer rez)
    {
        llResetScript();
    }
    
    touch_end(integer touched)
    {
        if(!llAgentInExperience(llDetectedKey(0)))
        {
            if(!debug)
            {
                return;
            }
        }
        if(llDetectedKey(0) != usr && active)
        {
            llRegionSayTo(llDetectedKey(0), 0, "Someone else is currently using this station.");
            return;
        }
        else if(usr == "" ^ usr == llDetectedKey(0))
        {
            active = TRUE;
            recipeBrowsing = FALSE;
            chosenRecipe = -2;
            usr = llDetectedKey(0);
            listener = llListen(channel, "", usr, "");
            page = 1;
            func_SendDataToServer("func=chkChar&uuid=" + (string)usr);
            llSetTimerEvent(time);
        }
    }
    
    timer()
    {
        func_Timeout(TRUE);
    }
    
    changed(integer change)
    {
        if(change & CHANGED_OWNER)
        {
            llResetScript();
        }
        if(change & CHANGED_INVENTORY)
        {
            llOwnerSay("Inventory update detected. Resetting station.");
            llResetScript();
        }
    }
    
    http_response(key id, integer status, list meta, string body)
    {
        if(id != onlineCommsKey || !active)
        {
            return;
        }
        if(llGetSubString(body, 0, 3) == "err:")
        {
            llRegionSayTo(usr, 0, llGetSubString(body, 4, -1));
            func_Timeout(FALSE);
            return;
        }
        if(recipeBrowsing)
        {
            if(chosenRecipe == -2)
            {
                llSetTimerEvent(time);
                func_ParseRecipes(body);
            }
            else
            {
                // return $this->gItemDetails['name'] . "=>" . $craftedAmount . "=>" . $exp . "=>" . $this->characterJobDetails['level'];
                list tmp = llParseString2List(body, ["=>"], []);
                integer s = Key2AppChan(usr, 1338);
                llRegionSayTo(usr, s, "::readyUpdate::" + (string)usr);
                llSleep(0.2);
                llRegionSayTo(usr, s, "::doUpdate::" + (string)usr);
                llDialog(usr,  "You have successfully crafted " + llList2String(tmp, 1) + "x " + llList2String(tmp, 0) + ". You have gained " + llList2String(tmp, 2) + " experience, and you are currently level " + llList2String(tmp, 3) + ".", ["OK"], channel);
                func_Timeout(FALSE);
            }
            return;
        } 
        list cmd = llParseString2List(body, [":"], []);
        if(llList2String(cmd, 0) == "success")
        {
            // Then what was successful?
            if(llList2String(cmd, 1) == "check")
            {
                // If check is successful, character has the appropriate job.
                charName = llList2String(cmd, -1);
                llDialog(usr, stationDesc + "\n\nHello, " + charName + "!\n\nWhat would you like to do?", func_OrderButtons(menu), channel);
            }
            else if(llList2String(cmd, 1) == "addjob")
            {
                if(llList2String(cmd,2) == "diffjob")
                {
                    llDialog(usr, "Sorry, but you already have a different job; please unregister your current job before becoming a " + jobName + ".", ["OK"], channel);
                    func_Timeout(FALSE);
                }
                else if(llList2String(cmd, 2) == "samejob")
                {
                    llDialog(usr, "You are already a " + jobName + ".", ["OK"], channel);
                    func_Timeout(FALSE);
                }
                else if(llList2String(cmd, 2) == (string)stationJobId)
                {
                    llDialog(usr, "Congratulations! You have become a " + jobName + ".", ["OK"], channel);
                    action = FALSE;
                    llSleep(3.0);
                    func_SendDataToServer("func=chkChar&uuid=" + (string)usr); // Open main menu once you have registered.
                    llSetTimerEvent(time);
                }
            }
            else if(llList2String(cmd, 1) == "rmjob")
            {
                if(llList2String(cmd, 2) == (string)stationJobId)
                {
                    llDialog(usr, charName + " is no longer a " + jobName + ".", ["OK"], channel);
                    func_Timeout(FALSE);
                }
            }
        } // fail:check:diffjob
        else if(llList2String(cmd, 0) == "fail")
        {
            if(llList2String(cmd, 1) == "check")
            {
                if(llList2String(cmd,2) == "diffjob")
                {
                    llDialog(usr, "Sorry, " + llList2String(cmd, -1) + ", you don't have the right job for this.", ["OK"], channel);
                    func_Timeout(FALSE);
                }
                else if(llList2String(cmd, 2) == "nojob")
                {
                    charName = llList2String(cmd, -1);
                    llDialog(usr, "Greetings, " + charName + "! You currently have no job. Would you like to become a "+jobName+"? You can only have one job.", ["Yes", "No"], channel);
                    action = 1;
                    llSetTimerEvent(time);
                }
            }
        }
        else if(llList2String(cmd, 0) == "chkstats")
        {
            if(llList2String(cmd, 1) == "nojob")
            {
                llDialog(usr, "You have no job.", ["OK"], channel);
            }
            else
            {
                string o = "You are currently level " + llList2String(cmd, 3) + ". Your total experience is " + llList2String(cmd, 1) + " and you need " + llList2String(cmd, 2) + " to level up.";
                llDialog(usr, o, ["OK"], channel);
            }
            func_Timeout(FALSE);
        }
    }
    
    listen(integer c, string n, key id, string m)
    {
        if(c != channel)
        {
            return;
        }
        if(m == "Craft")
        {
            // Pull up which recipes you can use.
            page = 1;
            recipeBrowsing = TRUE;
            func_SendDataToServer("func=getPage&page="+(string)page+"&uuid=" + (string)usr);
        }
        else if(m == "Unregister")
        {
            llDialog(usr, "This will unregister you as a " + jobName + ". You will lose all your experience and levels. Are you sure?", ["Yes", "No"], channel);
            action = 2;
            llSetTimerEvent(time);
        }
        else if(m == "Check stats")
        {
            func_SendDataToServer("func=chkStats&uuid=" + (string)usr);
        }
        else if(action == 4)
        {
            action = 3;
            if(!func_IsInteger(m))
            {
                llDialog(usr, m + " is not a valid number.", ["OK"], -1337);
                func_Timeout(FALSE);
                return;
            }
            
        }
        else if(m == "Yes")
        {
            /*
                Action list
                    1 - Register
                    2 - Unregister
                    3 - Crafting
            */
            if(action == 1)
            {
                func_SendDataToServer("func=register&uuid=" + (string)usr);
            }
            else if(action == 2)
            {
                func_SendDataToServer("func=remove&uuid=" + (string)usr);
            }
        }
        else if(m == "No" || m == "Cancel")
        {
            func_Timeout(FALSE);
        }
        else if(m == "<<")
        {
            --page;
            if(page < 1)
            {
                page = 1;
            }
            func_SendDataToServer("func=getPage&page="+(string)page+"&uuid=" + (string)usr);
        }
        else if(m == ">>")
        {
            ++page;
            func_SendDataToServer("func=getPage&page="+(string)page+"&uuid=" + (string)usr);
        }
        else
        {
            if(action == 3)
            {
                if(!func_IsInteger(m))
                {
                    llDialog(usr, m + " is not a valid number.", ["OK"], -1337);
                    func_Timeout(FALSE);
                    return;
                }
                else if(m == "0" || m == "Cancel")
                {
                    llDialog(usr, "Canceled.", ["OK"], -1337);
                    func_Timeout(FALSE);
                }
                func_SendDataToServer("func=create&uuid=" + (string)usr+"&recipeId=" + llList2String(llParseString2List(llList2String(recipes, chosenRecipe), ["=>"], []), 0) + "&amount=" + m);
            }
            else if(recipeBrowsing && chosenRecipe == -2)
            {
                chosenRecipe = llListFindList(recipeFiltered, [m]);
                if(chosenRecipe == -1)
                {
                    llDialog(usr, "((This recipe doesn't exist. Please alert the staff!))", ["OK"], channel);
                    func_Timeout(FALSE);
                    return;
                }
                else
                {
                    // At this stage we're going to tell the player which materials are needed.
                    list tmp = llParseString2List(llList2String(recipes, chosenRecipe), ["=>"], []);
                    list mats = llList2List(tmp, 2, 5);
                    integer i = (llGetListLength(mats) - 1);
                    integer x;
                    string info = "Crafting " + llList2String(tmp, 1) + " will require the following materials: \n";
                    list mData;
                    do
                    {
                        if(llList2String(mats, x) != "0" && llList2String(mats, x) != "")
                        {
                            mData = llParseString2List(llList2String(mats, x), [":"], []);
                            info += llList2String(mData, 1) + "x " + llList2String(mData, 0) + "\n";
                        }
                    }while(x++<=i);
                    info += "\nHow many would you like to craft? 0 to cancel";
                    action = 3;
                    llTextBox(usr, info, channel);
                }
            }
        }
    }
    
    dataserver(key id, string data)
    {
        if(id != dsKey)
        {
            return;
        }
        if(data == EOF)
        {
            // If we're at the end of the file, close the program and ready for operation.
            line = 0;
            llOwnerSay("Notecard parsed, crafting station ready.");
            if(debug)
            {
                llOwnerSay("Title: " + stationTitle);
                llOwnerSay("Desc: " + stationDesc);
                llOwnerSay("Job ID: " + (string)stationJobId);
            }
            llSetObjectName(stationTitle);
            return;
        }
        if(llGetSubString(data, 0, llSubStringIndex(data, ":")) == "Title:")
        {
            // Get the tile of the crafting station.
            data = llStringTrim(llGetSubString(data, (llSubStringIndex(data, ":") + 1), -1), STRING_TRIM);
            stationTitle = data;
        }
        else if(llGetSubString(data, 0, llSubStringIndex(data, ":")) == "Description:")
        {
            // A short description of the crafting station.
            data = llStringTrim(llGetSubString(data, (llSubStringIndex(data, ":") + 1), -1), STRING_TRIM);
            stationDesc = data;
        }
        else if(llGetSubString(data, 0, llSubStringIndex(data, ":")) == "Job ID:")
        {
            data = llStringTrim(llGetSubString(data, (llSubStringIndex(data, ":") + 1), -1), STRING_TRIM);
            if((integer)data > 0)
            {
                stationJobId = (integer)data;
            }
            else
            {
                llOwnerSay("Job ID value is not a recognizable number.");
                return;
            }
        }
        else if(llGetSubString(data, 0, llSubStringIndex(data, ":")) == "Job name:")
        {
            data = llStringTrim(llGetSubString(data, (llSubStringIndex(data, ":") + 1), -1), STRING_TRIM);
            jobName = data;
        }
        dsKey = llGetNotecardLine(notecard, line++);
    }
}
