"use client";

import React, { createContext, useState, useEffect } from 'react';
import Cookies from 'js-cookie';

export const IfUserLoginContext = createContext(null);

const Context = ({ children }) => {
    const [isLogin, setisLogin] = useState(false);

    useEffect(() => {

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


    return (
        <IfUserLoginContext.Provider value={{ isLogin, setisLogin }}>
            {children}
        </IfUserLoginContext.Provider>
    );
};

export default Context;
