string defaultTexture = "pouch";
integer deleting;
integer deleteNum;
integer selected;
key banker = NULL_KEY;
integer banking;
string serverURL = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/inventory_management_handler.php?";
key itemData;
key itemUsage;
key itemShow;
integer selectedItem;
string itemName;
string charName;
key ping(string data)
{
    return llHTTPRequest(serverURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], data);
}
list items = [
"",
"",
"",
"",
"",
"",
"",
"",
""
];
list names = [
"",
"",
"",
"",
"",
"",
"",
"",
""
];
list slots = [
"",
"",
"",
"",
"",
"",
"",
"",
""
];
list texts = [
"txt_1",
"txt_2",
"txt_3",
"txt_4",
"txt_5",
"txt_6",
"txt_7",
"txt_8",
"txt_9"
];
list nums = [
"",
"",
"",
"",
"",
"",
"",
"",
""
];
integer persNum = 90800; // Which number tells this script to do things.
string moneyName = "Money"; // Money unless otherwise specified.
integer moneyAmount;
integer linkchan;
integer squawk;
integer squawker;
integer Key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}

prepare()
{
    dChan = FALSE;
    deleting = FALSE;
    deleteNum = FALSE;
    selected = FALSE;
    banking = FALSE;
    banker = NULL_KEY;
    llListenRemove(dHandler);
    llListenRemove(squawker);
    squawk = Key2AppChan(llGetOwner(), 1338);
    squawker = llListen(squawk, "", "", "");
    items = [
    "",
    "",
    "",
    "",
    "",
    "",
    "",
    "",
    ""
    ];
    names = [
    "",
    "",
    "",
    "",
    "",
    "",
    "",
    "",
    ""
    ];
    llMessageLinked(LINK_SET, FALSE, "txt_10", NULL_KEY);
    integer i = llGetNumberOfPrims();
    integer x;
    do
    {
        string n = llGetLinkName(i);
        if(~llSubStringIndex(n, "slot_"))
        {
            x = ((integer)llGetSubString(n, -1, -1) - 1);
            slots = llListReplaceList(slots, [n], x, x);
            nums = llListReplaceList(nums, [(string)i], x, x);
            SetLinkTextureFast(i, TEXTURE_TRANSPARENT, ALL_SIDES);
            llSetLinkColor(i, <1,1,1>, ALL_SIDES);
        }
        --i;
    }
    while(i>=0);
    i = (llGetListLength(texts) - 1);
    do
    {
        llMessageLinked(LINK_SET, FALSE, llList2String(texts, i), NULL_KEY);
        --i;
    } while(i>=0);
}

integer slotTaken(string data)
{
    integer i = (llGetListLength(items) - 1);
    integer x;
    list tmp;
    do
    {
        tmp = llParseString2List(llList2String(items, x), [":"], []);
        if(llList2String(tmp, 0) == data)
        {
            return x;
        }
        ++x;
    } while(x <= i);
    
    return -1;
}

parseInventory(string data)
{
    list tmp;
    if(~llSubStringIndex(data, "fullinv:") && llSubStringIndex(data, "fullinv:") == 0)
    {
        // Nuke the thing!
        prepare();
        data = llStringTrim(llDeleteSubString(data, 0, 7), STRING_TRIM);
        tmp = llParseString2List(data, ["|"], []);
        list tmpData = llParseString2List(llList2String(tmp, 0), [","], []);
        list tmpNames = llParseString2List(llList2String(tmp, 1), ["§"], []);
        integer x;
        integer i = 8; // Zero-indexed; this starts at 9.
        integer y = 0;
        do
        {
            items = llListReplaceList(items, [llList2String(tmpData, i)], i, i);
            --i;
        }
        while(i>=0);
        moneyAmount = (integer)llList2String(tmpData, 9);
        moneyName = llList2String(tmpData, 10);
        integer z = (llGetListLength(tmpNames) - 1);
        i = 0;
        do
        {
        
            tmp = llParseString2List(llList2String(tmpNames, i), [","], []);
            integer num = slotTaken(llList2String(tmp, 0));
            if(num != -1)
            {
                names = llListReplaceList(names, [llList2String(tmp, 1)], num, num);
                string c = llDumpList2String(llListReplaceList(tmp, [], 0, 1), ",");
                vector col = rgb2sl((vector)c);
                //llOwnerSay((string)col);
                num = llList2Integer(nums, num);
                llSetLinkColor(num, col, ALL_SIDES);
            }
            ++i;
        }
        while(i <= z);

        x = (llGetListLength(items) - 1);
        do
        {
            integer num = llList2Integer(nums, x);
            string d = llList2String(items, x);
            if(d != "0")
            {
                tmp = llParseString2List(d, [":"], []);
                d = llList2String(tmp, 2);
                SetLinkTextureFast(num, d, ALL_SIDES);
                llMessageLinked(LINK_SET, (integer)llList2String(tmp, 1), llList2String(texts, x), NULL_KEY);
            }
            else
            {
                SetLinkTextureFast(num, TEXTURE_TRANSPARENT, ALL_SIDES);
                llMessageLinked(LINK_SET, FALSE, llList2String(texts, x), NULL_KEY);
            }
            --x;
        } while(x >= 0);
        //llSetText(moneyName + ": " + (string)moneyAmount, <1,1,1>, 1);
        llMessageLinked(LINK_SET, moneyAmount, "txt_10:"+moneyName, NULL_KEY);
    }
    else if(~llSubStringIndex(data, "money:"))
    {
        data = llStringTrim(llDeleteSubString(data, 0, 5), STRING_TRIM);
        list tmp = llParseString2List(data, [","], [""]);
        moneyAmount = llList2Integer(tmp, 0);
        moneyName = llList2String(tmp, 1);
        //llSetText(moneyName + ": " + (string)moneyAmount, <1,1,1>, 1);
    }
    
}
SetLinkTextureFast(integer link, string texture, integer face)
{
    // Obtain the current texture parameters and replace the texture only.
    // If we are going to apply the texture to ALL_SIDES, we need
    // to adjust the returned parameters in a loop, so that each face
    // keeps its current repeats, offsets and rotation.
    list Params = llGetLinkPrimitiveParams(link, [PRIM_TEXTURE, face]);
    integer idx;
    face *= face > 0; // Make it zero if it was ALL_SIDES
    // This part is tricky. The list returned by llGLPP has a 4 element stride
    // (texture, repeats, offsets, angle). But as we modify it, we add two
    // elements to each, so the completed part of the list has 6 elements per
    // stride.
    integer NumSides = llGetListLength(Params) / 4; // At this point, 4 elements per stride
    for (idx = 0; idx < NumSides; ++idx)
    {
        // The part we've completed has 6 elements per stride, thus the *6.
        Params = llListReplaceList(Params, [PRIM_TEXTURE, face++, texture], idx*6, idx*6);
    }
    llSetLinkPrimitiveParamsFast(link, Params);
}
vector rgb2sl( vector rgb )
{
    return rgb / 255;        //Scale the RGB color down by 255
}
integer trading;
integer tradeNum = 90900;
integer timeout = 120;
integer dChan;
integer dHandler;
integer isInteger(string data)
{
    if(((string)((integer)data) == data) && (integer)data > 0)
    {
        return TRUE;
    }
    return FALSE;
}
integer echoCh;
integer echoL;
default
{
    state_entry()
    {
        llSetText("", <1,1,1>, -1);
        llMessageLinked(LINK_SET, moneyAmount, "txt_10:wipe", NULL_KEY);
        if(!llGetAttached())
        {
            return;
        }
        linkchan = Key2AppChan(llGetOwner(), 9512);
        llPassTouches(PASS_ALWAYS);
        prepare();
        llSetLinkTexture(LINK_THIS, defaultTexture, ALL_SIDES);
        llMessageLinked(LINK_SET, 1, llGetScriptName(), "");
    }
    http_response(key id, integer status, list metadata, string body)
    {
        if(id == itemData)
        {
            list tmp = llParseString2List(body, [":::"], []);
            charName = llList2String(tmp, -1);
            string cmd = llList2String(tmp, 0);
            if(cmd == "echo")
            {
                list options = ["Show", "Destroy", "Cancel"];
                if(llList2String(tmp, 5) == "1") // If usable
                {
                    options = ["Use"] + options;
                }
                itemName = llList2String(tmp, 1);
                string desc = itemName + "\n\n" + llList2String(tmp, 2);
                if(llList2String(tmp, 3) != "0")
                {
                    desc = desc + "\n\nSells for: " + llList2String(tmp, 3) + " Crowns a piece.";
                }
                desc = desc + "\n\nCan carry a maximum of " + llList2String(tmp, 4) + ".";
                echoCh = Key2AppChan(llGetOwner(), 19);
                echoL = llListen(echoCh, "", llGetOwner(), "");
                llSetTimerEvent(30);
                llDialog(llGetOwner(), desc, options, echoCh);
            }
        }
    }    
    on_rez(integer start)
    {
        if(!llGetAttached())
        {
            llMessageLinked(LINK_SET, moneyAmount, "txt_10:wipe", NULL_KEY);
            prepare();
        }
        llSetText("", <1,1,1>, 1);
    }
    
    link_message(integer sender, integer function, string body, key id)
    {
        if(function == persNum)
        {
            if(deleting)
            {
                list tmp = llParseString2List(body, [":::"],[]);
                string n = llList2String(tmp, 0);
                body = llList2String(tmp, 1);
                n = llGetSubString(n, (llSubStringIndex(n, ":") + 1), -1);
                n = llStringTrim(n, STRING_TRIM);
                llSay(0, n + " destroyed " + (string)deleteNum + "x " + llList2String(names, selected) + ".");
                llSetTimerEvent(0.2);
            }
            parseInventory(body);
        }
        else if(function == linkchan)
        {
            // Check if link corresponds to the list.
            integer i = llListFindList(nums, [body]);
            if(i != -1)
            {
                string out = llList2String(names, i);
                if(out == "")
                {
                    llOwnerSay("Empty.");
                }
                else if(!dChan)
                {
                    if(!trading)
                    {
                        list tmp = llParseString2List(llList2String(items, i), [":"], []);
                        if(!banking)
                        {
                            //llOwnerSay(llList2String(tmp, 1) + "x " + out);
                            llListenRemove(echoL);
                            string isl = llList2String(items, i);
                            selectedItem = (integer)llList2String(llParseString2List(isl, [":"], []), 0);
                            itemData = ping("show&usr=" + (string)llGetOwner() + "&func=getDetails&itemId="+(string)selectedItem);
                        }
                        else if(banking == 1)
                        {
                            selected = i;
                            llTextBox(llGetOwner(), "How much of " + out + " would you like to store?", squawk);
                            banking = 2;
                            llSetTimerEvent(30);
                        }
                    }
                    else
                    {
                        llMessageLinked(LINK_THIS, (tradeNum + 1), out + "|" + llList2String(items, i), llGetOwner());
                        trading = FALSE;
                        llSetTimerEvent(0);
                    }
                }
                else if(dChan)
                {
                    dHandler = llListen(dChan, "", llGetOwner(), "");
                    deleting = (integer)llList2String(llParseString2List(llList2String(items, i), [":"], []), 0);
                    selected = i;
                    llTextBox(llGetOwner(), "How many of " + llList2String(names, i) + " would you like to destroy?", dChan);
                }
            }
        }
        else if(function == tradeNum)
        {
            if(body == "::stoptransaction::")
            {
                trading = FALSE;
                llSetTimerEvent(0);
            }
            else if(body == "giveitem")
            {
                trading = TRUE;
                llSetTimerEvent(timeout);
            }
            else if(body == "givemoney")
            {
                llMessageLinked(LINK_THIS, (tradeNum + 1), "givingmoney|" + moneyName + "|" + (string)moneyAmount, "");
            }
            else if(body == "::startdeletion::")
            {
                // Initiate the deletion process.
                dChan = Key2AppChan(llGetOwner(), 9899);
                llOwnerSay("Select the item you wish to destroy by touching its HUD icon.");
                llSetTimerEvent(timeout);
            }
        }
    }
    
    listen(integer c, string n, key id, string m)
    {
        if(c == dChan)
        {
            if(isInteger(m))
            {
                // Let's delete!
                if(deleting > 0)
                {
                    string out = (string)deleting + ":" + m;
                    deleteNum = (integer)m;
                    llMessageLinked(LINK_THIS, (tradeNum - 2), out, "");
                }
            }
        }
        else if(c == squawk)
        {
            if(!~llSubStringIndex(m, "storingRequest:") ^ (id != banker && id != NULL_KEY))
            {
                // Exit if we're not storing anything!
                return;
            }
            if(!banking)
            {
                list tmp = llParseString2List(m, [":"], []);
                string tmp1 = llList2String(tmp, 1);
                banker = id;
                llRegionSayTo(banker, squawk, "::key::");
                if(tmp1 == "select")
                {
                    banking = 1;
                    llOwnerSay("Please select which item you wish to store by touching the HUD icon.");
                }
                llSetTimerEvent(30);
            }
            if(banking == 2)
            {
                if(isInteger(m))
                {
                    list tmp = llParseString2List(llList2String(items, selected), [":"], []);
                    if((integer)m > (integer)llList2String(tmp, 1))
                    {
                        llDialog(llGetOwner(), "You don't have that much to store.", ["Close"], squawk);
                        llSetTimerEvent(0.2);
                        return;
                    }
                    else
                    {
                        llRegionSayTo(banker, squawk, llList2String(tmp,0) + ":" + m + ":" + (string)banker);
                    }
                }
                else
                {
                    llDialog(llGetOwner(), "You must specify a number.", ["Close"], squawk);
                }
                llSetTimerEvent(0.2);
            }
        }
        else if(c == echoCh)
        {
            if(m == "Use")
            {
                // Use code.
            }
            else if(m == "Show")
            {
                string tmp = llGetObjectName();
                llSetObjectName("(ITEMS) " + charName);
                llSay(0, "/me carries item '" + itemName + "' (ID: "+(string)selectedItem+") in their inventory.");
                llSetObjectName(tmp);
                llListenRemove(echoL);
                llSetTimerEvent(0);
            }
        }
    }
    
    timer()
    {
        // Time out!
        llSetTimerEvent(0);
        trading = FALSE;
        dChan = FALSE;
        deleting = FALSE;
        deleteNum = FALSE;
        selected = FALSE;
        banking = FALSE;
        banker = NULL_KEY;
        llListenRemove(dHandler);
        llListenRemove(echoL);
    }
    
    attach(key id)
    {
        if(llGetAttached())
        {
            // If we're attached, then we've had our attach request accepted.
            //llResetScript(); // So reset!
        }
    }

}