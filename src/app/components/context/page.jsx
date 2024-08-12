"use client";

import React, { createContext, useState, useEffect } from 'react';
import Cookies from 'js-cookie';
import axios from 'axios';

export const IfUserLoginContext = createContext(null);
export const userDetail = createContext(null);

const Context = ({ children }) => {

    const [userdetail, setUserdetail] = useState([]);
    const [isLogin, setisLogin] = useState(false);

    useEffect(() => {
        checkAdmin();
    }, [isLogin]);

    useEffect(() => {
        checkAdmin();
        const logincookie = Cookies.get("ua");
        if (logincookie === 'true') {
            setisLogin(true);
        } else {
            setisLogin(false);
        }
    }, []);

    const checkAdmin = async () => {
        try {
            const api = await axios.post("/api/auth/getuserrole");
            setUserdetail(api?.data?.userdata);
        }
        catch (err) {
            console.log(err?.response?.data?.message);
        }
    }

    return (
        <IfUserLoginContext.Provider value={{ isLogin, setisLogin }}>
            <userDetail.Provider value={{ userdetail, setUserdetail }}>
                {children}
            </userDetail.Provider>
        </IfUserLoginContext.Provider>
    );
};

export default Context;
