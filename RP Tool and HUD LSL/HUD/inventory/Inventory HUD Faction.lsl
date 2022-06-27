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
key inviteTarget;
key getRankList;
key changeRank;

integer faction; // NOT FALSE when in a faction.
integer leaving; // Are we leaving?
integer mode = 1; // 1 - Main menu, 2 - Membership mangement, 3 - Change Ranks
integer fMenuChannel;
integer fMenuListener;
integer fMenuPage = 1;
integer npcChannel;
integer npcListener;
integer npcStorePage = 1;
integer inviteChannel;
integer inviteListener;
integer sensing;
integer selectedRank;

float timeout = 30;

list sensed;
list sensedNames;

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
        if(hasPermission(["officer"]) || hasPermission(["leader"]))
        {
            tmp += ["Change rank"];
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
        vLstRtn = llListInsertList( llList2List( gLstMnu, vIdxBgn, vIdxBgn + 9 ), (list)("«" + vStrPag), 0xFFFFFFFF ) +
                  (list)("»" + vStrPag);
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
list ranksNameParsed(list tmp)
{
    integer y = (llGetListLength(sensed) - 1);
    integer x = 0;
    list out = [];
    do
    {
        string tmp = llList2String(llParseString2List(llList2String(sensed, x), [":"], []), 1);
        tmp = llStringTrim(llGetSubString(tmp, 0, 16), STRING_TRIM);
        out += [tmp];
        ++x;
    }while(x<=y);
    return out;
}
resetAll()
{
    llSetTimerEvent(0);
    leaving = FALSE;
    llListenRemove(inviteListener);
    llListenRemove(fMenuListener);
    llListenRemove(npcListener);
    sensed = [];
    sensedNames = [];
    sensing = FALSE;
    inviteTarget = "";
    npcStorePage = 1;
    selectedRank = FALSE;
    mode = FALSE;
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
                resetAll();
                fStatus = ping("status&func=whoAmI&usr=" + (string)llGetOwner());
            }
        }
    }

    timer()
    {
        resetAll();
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
            else if(sensing)
            {
                if(m == "«")
                {
                    llSetTimerEvent(timeout);
                    if((sensing - 1) != 0)
                    {
                        --sensing;
                    }
                    llDialog(llGetOwner(), "Invite member to " + factionName, paginate(sensing, sensedNames), fMenuChannel);
                }
                else if(m == "»")
                {
                    llSetTimerEvent(timeout);
                    ++sensing;
                    llDialog(llGetOwner(), "Invite member to " + factionName, paginate(sensing, sensedNames), fMenuChannel);
                }
                else
                {
                    integer pos = llListFindList(sensedNames, [(string)m]);
                    if(pos != -1)
                    {
                        inviteTarget = (key)llList2String(sensed, pos);
                        inviteChannel = Key2AppChan(inviteTarget, 17);
                        inviteListener = llListen(inviteChannel, "", inviteTarget, "");
                        sensing = FALSE;
                        llListenRemove(fMenuListener);
                        llDialog(inviteTarget, "You have been invited to join " + factionName + "!", ["Accept", "Decline"], inviteChannel);
                        llDialog(llGetOwner(), "Invited " + m + " to the faction.", ["OK"], 5623781);
                    }
                    else
                    {
                        llOwnerSay("Couldn't invite " + m + " to the faction.");
                    }
                }
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
            else if(m == "Invite Member")
            {
                llSetTimerEvent(timeout);
                sensing = 1;
                llSensor("", "", AGENT, 20, PI);
            }
            else if(m == "Change rank")
            {
                mode = 3;
                sensing = TRUE;
                npcStorePage = 1;
                llSetTimerEvent(timeout);
                llSensor("", "", AGENT, 20, PI);
            }
        }
        else if(c == fMenuChannel && mode == 2)
        {

        }
        else if(c == fMenuChannel && mode == 3)
        {
            if(inviteTarget != "")
            {
                if(m == "«")
                {
                    llSetTimerEvent(timeout);
                    if((npcStorePage - 1) != 0)
                    {
                        --npcStorePage;
                    }
                    getRankList = ping("membership&func=getRanks&usr=" + (string)llGetOwner() + "&page=" + (string)npcStorePage + "&faction=" + (string)faction);
                }
                else if(m == "»")
                {
                    ++npcStorePage;
                    getRankList = ping("membership&func=getRanks&usr=" + (string)llGetOwner() + "&page=" + (string)npcStorePage + "&faction=" + (string)faction);
                }
                else
                {
                    integer pos = llListFindList(sensedNames, [(string)m]);
                    if(pos == -1)
                    {
                        llOwnerSay("Could not find " + m + " in the list.");
                    }
                    else
                    {
                        list tmp = llParseString2List(llList2String(sensed, pos), [":"], []);
                        changeRank = ping("membership&func=promote&usr=" + (string)llGetOwner() + "&rankId=" + llList2String(tmp, 0) + "&faction=" + (string)faction + "&target=" + (string)inviteTarget);
                    }
                }
            }
            else if(sensing)
            {
                if(m == "«" && inviteTarget == "")
                {
                    llSetTimerEvent(timeout);
                    if((sensing - 1) != 0)
                    {
                        --sensing;
                    }
                    llDialog(llGetOwner(), "Change rank of player ", paginate(sensing, sensedNames), fMenuChannel);
                    return;
                }
                else if(m == "»" && inviteTarget == "")
                {
                    llSetTimerEvent(timeout);
                    ++sensing;
                    llDialog(llGetOwner(), "Change rank of player ", paginate(sensing, sensedNames), fMenuChannel);
                    return;
                }
                sensing = FALSE;
                integer pos = llListFindList(sensedNames, [(string)m]);
                if(pos != -1)
                {
                    inviteTarget = (key)llList2String(sensed, pos);
                    getRankList = ping("membership&func=getRanks&usr=" + (string)llGetOwner() + "&page=" + (string)npcStorePage + "&faction=" + (string)faction);
                }    
            }
        }
        else if(c == npcChannel)
        {

        }
        else if(c == inviteChannel)
        {
            if(m == "Accept")
            {
                llSetTimerEvent(timeout);
                llOwnerSay(llKey2Name(inviteTarget) + " has accepted your faction invitation.");
                fMembership = ping("membership&func=invite&faction=" + (string)faction + "&target=" + (string)inviteTarget + "&usr=" + (string)llGetOwner());
            }
            else
            {
                llOwnerSay(llKey2Name(inviteTarget) + " has declined your faction invitation.");
                llSetTimerEvent(0.2);
            }
        }
    }

    sensor(integer num)
    {
        integer i;
        string name;
        sensed = [];
        sensedNames = [];
        do
        {
            if(llDetectedKey(i) != llGetOwner())
            {
                name = llKey2Name(llDetectedKey(i));
                name = llStringTrim(llGetSubString(name, 0, 12), STRING_TRIM);
                sensed += [(string)llDetectedKey(i)];
                sensedNames += [(string)name];
            }
            ++i;
        }
        while(i<=(num-1));
        if(mode == 3)
        {
            llDialog(llGetOwner(), "Change rank of player ", paginate(sensing, sensedNames), fMenuChannel);
        }
        else
        {
            llDialog(llGetOwner(), "Invite member to " + factionName, paginate(sensing, sensedNames), fMenuChannel);
        }
    }

    http_response(key request_id, integer status, list metadata, string body)
    {
        if(~llSubStringIndex(body, "err:"))
        {
            llOwnerSay(llGetSubString(body, 4, -1));
            return;
        }
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
            llOwnerSay(body);
            // Key2AppChan(key, 1338);
            // factionupdate::targetkey
            if(~llSubStringIndex(body, "You have added"))
            {
                llRegionSayTo(inviteTarget, Key2AppChan(inviteTarget, 1338), "factionupdate::" + (string)inviteTarget);
            }
            llSetTimerEvent(0.1);
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
        else if(request_id == getRankList)
        {
            sensed = llParseString2List(body, [fSeparator], []);
            sensedNames = ranksNameParsed(sensed);
            llDialog(llGetOwner(), "Select rank ", paginate(1, sensedNames + ["«", "»"]), fMenuChannel);            
        }
        else if(request_id == changeRank)
        {
            list tmp = llParseString2List(body, [fSeparator], []);
            if(llList2String(tmp, 0) == "success")
            {
                llDialog(llGetOwner(), "You have changed the rank for " + llList2String(tmp, 1) + ".", ["OK"], fMenuChannel);
                llRegionSayTo(inviteTarget, Key2AppChan(inviteTarget, 1338), "factionupdate::" + (string)inviteTarget);
                llRegionSayTo(inviteTarget, 0, "Your rank has been updated.");
                llSetTimerEvent(0.1);
            }
        }
    }
    
}
