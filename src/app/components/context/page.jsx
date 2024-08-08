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

        const logincookie = Cookies.get("ua");

        if (logincookie === 'true') {
            setisLogin(true);
        } else {
            setisLogin(false);
        }
    }, []);


    useEffect(() => {
        const numberInputs = document.getElementsByTagName("input");

        const handleKeyDown = (event) => {
            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                event.preventDefault();
            }
        };

        const handleWheel = (event) => {
            event.preventDefault();
        };
        Array.from(numberInputs).forEach((input) => {
            input.addEventListener('keydown', handleKeyDown);
            input.addEventListener('wheel', handleWheel);
        });
        return () => {
            Array.from(numberInputs).forEach((input) => {
                input.removeEventListener('keydown', handleKeyDown);
                input.removeEventListener('wheel', handleWheel);
            });
        };
    }, []);


    const checkAdmin = async () => {
        const email = "faizanrasheed169@gmail.com";
        try {
            const api = await axios.post("/api/auth/getuserrole", { email });
            setUserdetail(api?.data?.userdata || []);
        }
        catch (err) {
            console.log(err.response.data.message);
        }
    }

    return (
        <IfUserLoginContext.Provider value={{ isLogin, setisLogin }}>
            <userDetail.Provider value={userdetail}>
                {children}
            </userDetail.Provider>
        </IfUserLoginContext.Provider>
    );
};

export default Context;
