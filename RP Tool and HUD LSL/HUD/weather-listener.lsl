integer cChan = -235798; // Channel for talking to the station.
integer kqChan = -235792; // KQ channel.
//integer cChan = -2222; // debug channel
string descKey = "{ahg81332-11er-3221-agt3-8tyo2d004333}"; // For authentication.
key default_texture = "71b3d333-5c3b-b71b-3c1a-aa8dbf49a708";
string curW; // Store the weather value.
string curT = NULL_KEY;
string weather = "No weather server installed in region or response hasn't been received yet.";
string extra;
resetVars()
{
    curW = "";
    curT = NULL_KEY;
    weather = "No weather server installed in region or response hasn't been received yet.";
}

string regionName = "Clockwork";

integer getCurHeight()
{
    integer z;
    vector pos = (vector)llList2String(llGetObjectDetails(llGetOwner(), [OBJECT_POS]), 0);
    z = (integer)pos.z;
    return z;
}

integer cListen;
integer kqListen;
integer notInKQ = TRUE;
default
{
    state_entry()
    {
        llSetText("", <1,1,1>, 0);
        llSetTexture(TEXTURE_TRANSPARENT, ALL_SIDES);
        llSetTexture(default_texture, 2);
        llSetAlpha(0, ALL_SIDES);
        if(llGetRegionName() != regionName)
        {
            return;
        }
        integer pos = getCurHeight();
        if(pos < 2990 || pos > 3990)
        {
            cListen = llListen(cChan, "", "", "");
            llRegionSay(cChan, "weather");
            notInKQ = TRUE;
        }
        else
        {
            kqListen = llListen(kqChan, "", "", "");
            llRegionSay(kqChan, "weather");
            notInKQ = FALSE;
        }
        llSetTimerEvent(5);
    }
    touch_end(integer touched)
    {
        if(llDetectedKey(0) == llGetOwner())
        {
            llOwnerSay("--Current weather--\n" + curW + extra);
        }
    }
    timer()
    {
        llSetTimerEvent(0);
        integer pos = getCurHeight();
        if((pos < 2990 || pos > 3990) && !notInKQ)
        {
            llListenRemove(kqListen);
            notInKQ = TRUE;
            resetVars();
            cListen = llListen(cChan, "", "", "");
        }
        else if((pos > 2990 && pos < 3990) && notInKQ)
        {
            llListenRemove(cListen);
            notInKQ = FALSE;
            resetVars();
            kqListen = llListen(kqChan, "", "", "");
        }
        llSetTimerEvent(5);
    }
    attach(key id)
    {
        llResetScript();
    }
    on_rez(integer s) {
            llSetTimerEvent(0);
            llSetText("", <1,1,1>, 1);
            llListenRemove(cListen);
            llListenRemove(kqListen);
            llSetTexture(TEXTURE_TRANSPARENT, ALL_SIDES);
    }    

    listen(integer c, string n, key id, string m) {
        if(llList2String(llGetObjectDetails(id, [OBJECT_DESC]), 0) == descKey) {
            list tmp = llParseString2List(m, ["|"], [""]);
            if(m != curW + "|" + curT) {
                if(curW != llList2String(tmp, 0)) {
                    curW = llList2String(tmp, 0);
                    extra = llList2String(tmp, 2);
                    //llSay(0, curW);
                    llOwnerSay("--Current weather--\n" + curW + llList2String(tmp, 2));
                }
                if(llList2String(tmp, 1) != curT)
                    {
                        curT = llList2String(tmp, 1);
                        llSetTexture(curT, 2);
                        llSetAlpha(1, ALL_SIDES);
                    }
                    if(llGetListLength(tmp) > 2)
                    {
                        //llSetText(curW + llList2String(tmp, 2), <1,1,1>, 1);
                        weather = curW + llList2String(tmp, 2);
                    }
                    else
                    {
                        llSetText(curW, <1,1,1>, 1);
                        weather = curW;
                    }
                    
            }

        }
    }
    changed(integer change)
    {
        if(change & CHANGED_OWNER || change & CHANGED_REGION)
        {
            llResetScript();
        }
    }
}