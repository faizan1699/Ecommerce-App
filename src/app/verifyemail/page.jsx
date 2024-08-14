"use client";
// pages/verifyemail.js

import React, { useEffect, useState } from "react";

import axios from "axios";
import Link from "next/link";

import { useRouter } from "next/navigation";
import { showAlert } from "../components/alert/alert";

export default function VerifyEmailPage() {

    const router = useRouter();
    const [token, setToken] = useState("");
    const [verified, setVerified] = useState(false);


    useEffect(() => {
        const urlToken = window.location.search.split("=")[1];
        setToken(urlToken || "");
    }, []);

    const verifyUserEmail = async () => {

        try {

            const response = await axios.post('/api/mail/verifynewuser', { token });
            router.push('/login');
            setVerified(true);
            showAlert("success", "email verified", response?.data?.message, 3000);

        } catch (error) {

            setVerified(false);
            console.log("error from verify email frontend", error);
            showAlert("error", "Email not Verified", error?.response?.data?.message, 5000);

        }

    };

    useEffect(() => {
        if (token.length > 0) {
            verifyUserEmail();
        }
    }, [token]);

    return (
        <div className="flex flex-col items-center justify-center mt-8 py-2 bg-gray-100">
            <h2 className="text-4xl mb-8 font-black">Verify Email</h2>


            <h2 className="p-2 bg-orange-500 text-black mb-8">{token ? `${token}` : "No token"}</h2>

            {verified ? (
                <div className="text-center">
                    <h2 className="text-2xl mb-4">Email Verified Successfully!</h2>
                    <Link href="/login">
                        <p className="text-blue-500 hover:underline">Login</p>
                    </Link>
                </div>
            ) : (
                <div className="text-center">
                    <h2 className="text-2xl bg-red-500 text-white px-2 py-1 rounded-lg">not verified yet</h2>
                </div>
            )}
        </div>
    );
}
