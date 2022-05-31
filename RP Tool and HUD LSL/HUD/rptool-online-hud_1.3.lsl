integer channel;

integer buttonLinkNum = 0;
string buttonsname = "rptool_buttons";

string discord = "https://discord.gg/AjD7RFBhdh";

list immutable = [
    "6", "ic",
    "5", "ooc",
    "4", "afk",
    "1", "save",
    "2", "load",
    "3", "newchar"
];

integer page = 1;

list buttons;

list buttonsOne = [ 

    "0|togglename|1a02227d-c6da-e0d2-1b9c-a8f7a6dd3771",
    "6|silentroll|11924f78-5ee3-3f44-5259-4edcea9ad192",
    "5|roll|6a3a5609-cfb9-58e4-8120-a8084abff5e3",
    "2|changetitle|3f220111-15c4-0c9b-74bf-5225fc06b6fe",
    "3|changeconstant|65e73037-7b7c-57bf-f16c-0d235f3c0c6c",
    "4|help|cb12dfb9-b105-58ad-5eb5-f20e122b5e43"

];

list buttonsTwo = [ 

    "0|chatrange|80efa65f-879f-46f4-fda0-2287c43d4c87",
    "6|resetcharacter|b0f02d06-e868-aca0-a8dc-79830f665d3d",
    "5|support|d6d0ee73-5bbd-22b9-6d3d-041becb22b51",
    "2|percent|c31e66ac-4afb-5df5-f2dc-0a74f1d157f8",
    "3|reset|1b19ae47-4f01-8531-52a9-9bcb094553d3",
    "4|deletecharacter|7abdbe3a-819a-9319-3485-723f900af59f"
    

];

list buttonsThree = [
    "0|discord|e2030753-62f2-33b0-5216-4af2e9350a9e",
    "6|null|8dcd4a48-2d37-4909-9f78-f7a9eb4ef903",
    "5|null|8dcd4a48-2d37-4909-9f78-f7a9eb4ef903",
    "2|null|8dcd4a48-2d37-4909-9f78-f7a9eb4ef903",
    "3|null|8dcd4a48-2d37-4909-9f78-f7a9eb4ef903",
    "4|null|8dcd4a48-2d37-4909-9f78-f7a9eb4ef903" // This will always be reserved for staff.
];
string staffString = "4|openstaffmenu|1cfc3cac-51a4-97fd-4304-cf034a1d5324";
integer staffLinkMsgNum = -10;
key onlineCommsKey;
string url = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/inventory_staff_handler.php?";

string desc = "rptool-hud";
integer coloured = TRUE;


pagination() 
{
    if(page > 3)
    {
        page = 1;
    }
    else if(page < 1)
    {
        page = 3;
    }
    if(page == 1)
    {
        buttons = buttonsOne;
    }
    else if(page == 2)
    {
        buttons = buttonsTwo;
    }
    else if(page == 3)
    {
        buttons = buttonsThree;
    }
    
    integer max = 5;
    integer x = 0;
    string texture;
    list tmp;
    integer face;
    for(;x<=max;x++)
    {
        tmp = llParseString2List(llList2String(buttons, x), ["|"], []);
        texture = llList2String(tmp, 2);
        face = (integer)llList2String(tmp, 0);
        llSetLinkTexture(buttonLinkNum, texture, face);
    }
}
getButtonLinkNum()
{
    llSetObjectDesc(desc);
    integer x = 1;
    integer i = llGetNumberOfPrims();
    for(;x<=i;x++)
    {
        if(llGetLinkName(x) == buttonsname)
        {
            buttonLinkNum = x;
            x = i; 
        }
        
    }
    
    if(!buttonLinkNum)
    {
        llOwnerSay("Cannot find a valid button object. Please right click the button field between the arrows and rename the linked object to: "+buttonsname);
    return;
    }
}
integer Key2AppChan(key ID, integer App) {
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
sendDataToServer(string data)
{
    onlineCommsKey = llHTTPRequest(url, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], data);
}
default
{
    state_entry()
    {
        channel = Key2AppChan(llGetOwner(), 1337);
        getButtonLinkNum();
        pagination();
        llSetTimerEvent(0.1);
        sendDataToServer("func=chkStaff");
    }
    
    http_response(key id, integer status, list meta, string data)
    {
        if(id == onlineCommsKey && data == "::isStaffMember::")
        {
            // Then update the list to create the menu.
            buttonsThree = llListReplaceList(buttonsThree, [staffString], -1, -1);
        }
    }
    
    changed(integer change)
    {
        if(change & CHANGED_OWNER)
        {
            llResetScript();
        }

    }
    
    on_rez(integer start)
    {
        if(llGetAttached())
        {
            llResetScript(); 
        }
    }
    
    attach(key agent)
    {
        if(llGetAttached())
        {
            llResetScript();
        }
    }
    
    touch(integer touched)
    {
        if(llDetectedKey(0) != llGetOwner())
        {
            return;
        }
        integer obj = llDetectedLinkNumber(0);
        if(obj == buttonLinkNum) 
        {
            
            integer touchedFace = llDetectedTouchFace(0);
            if(!coloured)
            {
                coloured = TRUE;
                llSetLinkColor(obj, <0,1,0>, touchedFace);
            }
            
        }
        else if(llGetLinkName(obj) == llGetObjectName()) 
        {
            integer touchedFace = llDetectedTouchFace(0);
            if(touchedFace > 0)
            {
                if(!coloured)
                {
                    coloured = TRUE;
                    llSetLinkColor(obj, <0,1,0>, touchedFace);
                }
            }
        }
    }
    
    timer()
    {
        llSetTimerEvent(0);
        if(coloured)
        { 
            coloured = FALSE;
            integer i = llGetNumberOfPrims();
            integer x = 1;
            for(;x<=i;x++)
            {
                if(llGetLinkName(x) == llGetObjectName() || llGetLinkName(x) == buttonsname)
                {
                    llSetLinkColor(x, <1,1,1>, ALL_SIDES);
                }
            }
        }
    }
    
    touch_start(integer touched)
    {
        if(llDetectedKey(0) != llGetOwner())
        {
            return;
        }
        
        integer whichbutton;
        integer obj = llDetectedLinkNumber(0);
        integer face = llDetectedTouchFace(0);
        

        if(obj == buttonLinkNum)
        {
            if(!coloured)
            {
                coloured = TRUE;
                llSetLinkColor(obj, <0,1,0>, face);
            }
            
            if(face == 7) 
            {
                page = (page - 1);
                pagination();
            }
            else if(face == 1) 
            {
                page = (page + 1);
                pagination();
            }
            else 
            {
                integer aFace;
                integer max = (llGetListLength(buttons) - 1);
                integer x;
                list tmp;
                for(;x<=max;x++)
                {
                    tmp = llParseString2List(llList2String(buttons, x), ["|"], []);
                    aFace = llList2Integer(tmp, 0);
                    if(aFace == face)
                    {
                        string out = llList2String(tmp,1);
                        if(out == "discord") // Open Discord, don't send a command to the RP tool.
                        {
                            llLoadURL(llGetOwner(), "Open this site to join our Discord!", discord);
                            return;
                        }
                        else if(out == "openstaffmenu") // Open Staff menu, don't send a command to the RP tool.
                        {
                            llMessageLinked(LINK_SET, staffLinkMsgNum, out, "");
                            return;
                        }
                        llWhisper(channel, out);
                        return;
                    }
                }
            }
        }
        else if(llGetLinkName(obj) == llGetObjectName() && face > 0)
        {
            if(!coloured)
            {
                coloured = TRUE;
                llSetLinkColor(obj, <0,1,0>, face);
            }
            
            integer pos = (llListFindList(immutable, [(string)face]) +1);
            llWhisper(channel, llList2String(immutable, pos));
        }
    }
    
    touch_end(integer touched)
    {
        if(llDetectedKey(0) != llGetOwner())
        {
            return;
        }
        integer obj = llDetectedLinkNumber(0);
        if(obj == buttonLinkNum)
        {
            llSetTimerEvent(1.0);
            coloured = FALSE;
            integer touchedFace = llDetectedTouchFace(0);
            llSetLinkColor(obj, <1,1,1>, ALL_SIDES);
            
            
        }
        else if(llGetLinkName(obj) == llGetObjectName()) 
        {
            llSetTimerEvent(1.0);
            integer touchedFace = llDetectedTouchFace(0);
            if(touchedFace > 0)
            {
                coloured = FALSE;
                llSetLinkColor(obj, <1,1,1>, ALL_SIDES);
            }
        }
        

    }
}

