string startCall = "::openfaction::";
string serverURL = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/factions/faction_handler.php?";
string defaultName = "Inventory";
string npcName = "NPC";
string fSeparator = "::";
string fOtherSeparator = "&&";
string factionName;
string factionRank;
string factionPronoun;
string rankPermissions;

list menu = [
    "Stats",
    "Cur. Mission",
    "Leave",
    "Cancel"
];

key fMembership; // For membership requests
key fStatus; // Status requests
key fMissions; // Mission requests.
// Bake NPC functions into this script
key npcVendorGeneral;
key npcVendorViewItem;
key npcVendorItemData;
key npcVendorMakeTransaction;

integer faction; // NOT FALSE when in a faction.
integer leaving; // Are we leaving?
integer mode = 1; // 1 - Main menu, 2 - Membership mangement
integer fMenuChannel;
integer fMenuListener;
integer fMenuPage = 1;
integer npcChannel;
integer npcListener;
integer npcStorePage = 1;

key ping(string data) // Ping the server
{
    return llHTTPRequest(serverURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], data);
}

integer Key2AppChan(key ID, integer App) 
{ // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}

string factionDetails()
{
    string out;
    out = "You are a " + factionRank + " of " + factionName + ".\n\nWhat would you do, " + factionPronoun + "?";
    return out;
}

openMenu(integer num)
{
    if(num == 1)
    {
        llListenRemove(fMenuListener);
        fMenuChannel = Key2AppChan(llGetOwner(), 12);
        fMenuListener = llListen(fMenuChannel, "", llGetOwner(), "");
        list tmp = menu;
        if(hasPermission(["seeMembers"]))
        {
            tmp += ["Membership"];
        }
        mode = 1;
        llDialog(llGetOwner(), factionDetails(), paginate(fMenuPage, tmp), fMenuChannel);
    }
    else if(num == 2)
    {
        // Membership menu!
        list tmp = [
            
        ];
        if(hasPermission(["seeMembers"]))
        {
            tmp += ["Member List"];
        }
        if(hasPermission(["factionInvite"]))
        {
            tmp += ["Invite Member"];
        }
        if(hasPermission(["factionKick"]))
        {
            tmp += ["Kick Member"];
        }
        tmp += ["Cancel"];
        llDialog(llGetOwner(), "Membership options", paginate(0, tmp), fMenuChannel);
    }
}

list paginate( integer vIdxPag, list gLstMnu ){
    list vLstRtn;
    if ((gLstMnu != []) > 12){ //-- we have more than one possible page
        integer vIntTtl = -~((~([] != gLstMnu)) / 10);                                 //-- Total possible pages
        integer vIdxBgn = (vIdxPag = (vIntTtl + vIdxPag) % vIntTtl) * 10;              //-- first menu index
        string  vStrPag = llGetSubString( "                     ", 21 - vIdxPag, 21 ); //-- encode page number as spaces
         //-- get ten (or less for the last page) entries from the list and insert back/fwd buttons
        vLstRtn = llListInsertList( llList2List( gLstMnu, vIdxBgn, vIdxBgn + 9 ), (list)(" «" + vStrPag), 0xFFFFFFFF ) +
                  (list)(" »" + vStrPag);
    }else{ //-- we only have 1 page
        vLstRtn = gLstMnu; //-- just use the list as is
    }
    return //-- fix the order for [L2R,T2B] and send it out
      llList2List( vLstRtn, -3, -1 ) + llList2List( vLstRtn, -6, -4 ) +
      llList2List( vLstRtn, -9, -7 ) + llList2List( vLstRtn, -12, -10 );
}

integer hasPermission(list perms)
{
    if(~llSubStringIndex(rankPermissions, "leader"))
    {
        return TRUE; // Leaders have full access.
    }
    else
    {
        integer x = 0;
        integer i = (llGetListLength(perms) - 1);
        do
        {
            if(!~llSubStringIndex(rankPermissions, llList2String(perms, x)))
            {
                return FALSE; // FALSE if a permission required is not found in the list.
            }
        } while(x++ <= i);
        return TRUE;
    }
}
default
{
    state_entry()
    {
        
    }

    changed(integer change)
    {
        if(change & CHANGED_OWNER)
        {
            llResetScript();
        }
    }

    link_message(integer sender_num, integer num, string str, key id)
    {
        if(sender_num == llGetLinkNumber() && num == 0)
        {
            if(str == startCall)
            {
                fStatus = ping("status&func=whoAmI&usr=" + (string)llGetOwner());
            }
        }
    }

    timer()
    {
        llSetTimerEvent(0);
        leaving = FALSE;
    }

    listen(integer c, string n, key id, string m)
    {
        if(m == "Cancel")
        {
            llListenRemove(fMenuListener);
            llListenRemove(npcListener);
            return;
        }
        if(c == fMenuChannel && mode == 1)
        {
            if(leaving)
            {
                // Leave faction.
                if(m == "Yes")
                {
                    fStatus = ping("membership&func=leaveFaction&usr=" + (string)llGetOwner());
                }
                leaving = FALSE;
                llListenRemove(fMenuListener);
            }
            else if(m == "Stats")
            {
                llOwnerSay("Faction status here, when applicable.");
            }
            else if(m == "Cur. Mission")
            {
                llOwnerSay("Current faction mission progress here, if any.");
            }
            else if(m == "Membership")
            {
                // Membership options here
                openMenu(2);
            }
            else if(m == "Leave")
            {
                leaving = TRUE;
                llDialog(llGetOwner(), "Really leave faction? You have 10 seconds before timeout.", ["Yes", "No"], fMenuChannel);
                llSetTimerEvent(10);
            }
        }
        else if(c == fMenuChannel && mode = 2)
        {

        }
        else if(c == npcChannel)
        {

        }
    }

    http_response(key request_id, integer status, list metadata, string body)
    {
        if(request_id == fStatus)
        {
            list tmp = llParseStringKeepNulls(body, [fSeparator], []);
            string cmd = llList2String(tmp, 0);
            if(cmd == "nofaction")
            {
                llOwnerSay("You're not in a faction.");
                return;
            }
            else if(cmd == "wrongfaction")
            {
                llOwnerSay("An error has occurred; you are listed as being in a faction but there's an ID mismatch. Please report issue to staff");
                return;
            }
            else if(cmd == "whoAmI")
            {
                tmp = llParseString2List(llList2String(tmp, 1), ["&&"], []);
                faction = (integer)llList2String(tmp, 3);
                factionPronoun = llList2String(tmp, 1);
                factionRank = llList2String(tmp, 2);
                factionName = llList2String(tmp, 0);
                rankPermissions = llList2String(tmp, 4);
                openMenu(1);
            }
            else if(cmd == "leaving")
            {
                llSetObjectName("");
                llOwnerSay(llList2String(tmp, 1));
                llSetObjectName(defaultName);
                llSay(Key2AppChan(llGetOwner(), 1338), "factionupdate::" + (string)llGetOwner());
            }
        }
        else if(request_id == fMembership)
        {

        }
        else if(request_id == fMissions)
        {

        }
        else if(request_id == npcVendorGeneral)
        {

        }
        else if(request_id == npcVendorViewItem)
        {

        }
        else if(request_id == npcVendorItemData)
        {

        }
        else if(request_id == npcVendorMakeTransaction)
        {

        }
    }
    
}
