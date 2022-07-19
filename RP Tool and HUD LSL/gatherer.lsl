string url = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/inventory_gathernode_handler.php?";
key ping(string data)
{
    return llHTTPRequest(url, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "" + data);
}
string func_FilterComments(string data) // Nuke all comments from the card.
{
    return llStringTrim(llList2String(llParseStringKeepNulls(data, ["#"], []), 0), STRING_TRIM);
}
integer Key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
list order_buttons(list buttons)
{
    return llList2List(buttons, -3, -1) + llList2List(buttons, -6, -4)
         + llList2List(buttons, -9, -7) + llList2List(buttons, -12, -10);
}
func_updateInventory()
{
    integer s = Key2AppChan(uId, 1338);
    llRegionSayTo(uId, s, "::readyUpdate::" + (string)uId);
    llSleep(0.2);
    llRegionSayTo(uId, s, "::doUpdate::" + (string)uId);
}
key uId; // Current user.
string cardMode;
integer cardLine = 1;
float time = 30;
key readCardId;
string cardName = "gatherer_settings";
list admins;
list items;
list itemIds;
integer inUse;
integer selected;
integer chan;
integer lChan;
integer deactivated;
key getItemDetails;
key viewItems;
key gatherItems;
func_Reset()
{
    llSetTimerEvent(0);
    uId = "";
    inUse = FALSE;
    selected = FALSE;
    llListenRemove(lChan);
}
list usrMenuOne = ["Gather", "Cancel"];
default
{
    state_entry()
    {
        llSetText("((Booting gathernode, standby...))", <1,1,1>, 1);
        readCardId = llGetNotecardLine(cardName, cardLine);
    }
    
    http_response(key id, integer status, list meta, string data)
    {
        if(llGetSubString(data, 0, 3) == "err:")
        {
            if(!inUse)
            {
                llWhisper(0, "Server error: " + llGetSubString(data, 4, -1));
            }
            else
            {
                llRegionSayTo(uId, 0, llGetSubString(data, 4, -1));
            }
            return;
        }
        if(id == getItemDetails)
        {
            items = [];
            itemIds = [];
            list tmp = llParseString2List(data, ["&&"], []);
            integer i = (llGetListLength(tmp) - 1);
            integer x = 0;
            while(x <= i)
            {
                items += [llStringTrim(llGetSubString(llList2String(llParseString2List(llList2String(tmp, x), ["::"], []), 1), 0, 10), STRING_TRIM)];
                itemIds += [llStringTrim(llList2String(llParseString2List(llList2String(tmp, x), ["::"], []), 0), STRING_TRIM)];
                ++x;
            }
            items = llList2List(items, 0, 10);
            itemIds = llList2List(itemIds, 0, 10);
            llSetText("", <1,1,1>, -1);
        }
        else if(id == viewItems)
        {
            list tmp = llParseString2List(data, ["::"], []);
            string out = llList2String(tmp, 1) + "\n" + llList2String(tmp, 2) + "\nMax stack: " + llList2String(tmp, 7) + ".";
            llDialog(uId, out, order_buttons(usrMenuOne), chan);
            llSetTimerEvent(time);
        }
        else if(id == gatherItems)
        {
            list tmp = llParseString2List(data, ["::"], []);
            llRegionSayTo(uId, 0, "You have successfully gathered " + llList2String(tmp, 1) + "x " +
                                  llList2String(tmp, 2)+". You have " + llList2String(tmp, 3) + " attempts remaining."
            );         
            func_updateInventory();   
            func_Reset();
        }
    }
    timer()
    {
        func_Reset();
    }
    dataserver(key id, string data)
    {
        if(id != readCardId)
        {
            return;
        }
        if(data == EOF)
        {
            getItemDetails = ping("func=getItemDetails&uuid=1&itemId=0&items=" + llDumpList2String(itemIds, ","));
        }
        else
        {
            data = func_FilterComments(data);
            if(data == "--admins" || data == "--gatherables")
            {
                cardMode = data;
            }
            else
            {
                if(cardMode == "--admins")
                {
                    admins += (string)data;
                }
                else if(cardMode == "--gatherables")
                {
                    itemIds += [(string)llStringTrim(data, STRING_TRIM)];
                }
            }
            readCardId = llGetNotecardLine(cardName, ++cardLine);
        }
    }
    changed(integer change)
    {
        if(change & CHANGED_INVENTORY)
        {
            llResetScript();
        }
        if(change & CHANGED_REGION_START)
        {
            llResetScript();
        }
    }
    listen(integer c, string n, key id, string m)
    {
        if(m == "Cancel")
        {
            func_Reset();
            return;
        }
        if(m == "Gather")
        {
            gatherItems = ping("func=gatherItem&itemId=" + (string)selected + "&uuid=" + (string)uId);
        }
        else if(m == "Activate" || m == "Deactivate")
        {
            if(deactivated)
            {
                deactivated = FALSE;
            }
            else
            {
                deactivated = TRUE;
            }
        }
        else
        {
            integer pos = llListFindList(items, [m]);
            if(pos == -1)
            {
                func_Reset();
                return;
            }
            selected = (integer)llList2String(itemIds, pos);
            viewItems = ping("func=viewItem&uuid="+(string)uId+"&itemId=" + (string)selected);
        }
    }

    touch_end(integer total_number)
    {
        if(deactivated && llListFindList(admins, [(string)llKey2Name(llDetectedKey(0))]) == -1)
        {
            return;
        }
        if(uId != llDetectedKey(0) && inUse)
        {
            return;
        }
        if(!llAgentInExperience(llDetectedKey(0)))
        {
            return;
        }
        uId = llDetectedKey(0);
        inUse = 1;
        chan = Key2AppChan(uId, 130);
        lChan = llListen(chan, "", uId, "");
        llSetTimerEvent(time);
        list stf = ["Cancel"];
        if(deactivated && llListFindList(admins, [(string)llKey2Name(llDetectedKey(0))]) != -1)
        {
            stf += ["Activate"];
        }
        else if(!deactivated && llListFindList(admins, [(string)llKey2Name(llDetectedKey(0))]) != -1)
        {
            stf += ["Deactivate"];
        }
        llDialog(uId, "You may gather the following here:", order_buttons(items + stf), chan);
    }
}
