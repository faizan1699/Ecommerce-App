"use client";

import React, { useContext, useState } from 'react';
import logo from '../../assets/logo/logo.png';
import Image from 'next/image';
import Link from 'next/link';
import axios from 'axios';
import avatar from "../../assets/imgs/avatar.png";

import { userDetail } from '../context/page';
import { IfUserLoginContext } from '../context/page';
import { showAlert } from '../alert/alert';
import { useRouter } from 'next/navigation';

const navMenu = [
    { title: "Home", href: "/home" },
    { title: "Products", href: "/products" },
    { title: "Contact", href: "/contact" },
];

const Topbar = () => {


    const router = useRouter();
    const { isLogin, setisLogin } = useContext(IfUserLoginContext);
    const { userdetail, setUserdetail } = useContext(userDetail);

    const linkclass = "rounded-md hover:bg-gray-700 px-3 py-2 text-sm font-medium text-white";

    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    const toggleUserMenu = () => setIsMenuOpen(!isMenuOpen);
    const toggleMobileMenu = () => setIsMobileMenuOpen(!isMobileMenuOpen);

    const Logout = async () => {
        try {
            const response = await axios.post("/api/auth/logout");
            showAlert("success", "Success", response?.data?.message, 3000);
            router.push("/");
            setisLogin(false);
            setIsMenuOpen(false);
            setUserdetail([]);
        } catch (error) {
            console.log(error);
            showAlert("error", "Error", error?.response?.data?.message, 5000);
        }
    };

    return (
        <div>
            <nav className="bg-gray-800">
                <div className="mx-auto max-w-7xl px-2 sm:px-6 lg:px-8">
                    <div className="relative flex h-16 items-center justify-between">
                        <div className="absolute inset-y-0 left-0 flex items-center sm:hidden">
                            <button
                                type="button"
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                                aria-controls="mobile-menu"
                                aria-expanded={isMobileMenuOpen}
                                onClick={toggleMobileMenu}
                            >
                                <span className="sr-only">Open main menu</span>
                                <svg
                                    className={`h-6 w-6 transition-transform duration-300 ${isMobileMenuOpen ? 'hidden' : 'block'}`}
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    strokeWidth="1.5"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                                    />
                                </svg>
                                <svg
                                    className={`h-6 w-6 transition-transform duration-300 ${isMobileMenuOpen ? 'block' : 'hidden'}`}
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    strokeWidth="1.5"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>

                        {/* Desktop Menu */}
                        <div className="flex flex-1 items-center justify-center sm:items-stretch sm:justify-start">
                            <div className="flex flex-shrink-0 items-center">
                                <Link href="/">
                                    <Image src={logo} className="h-8 w-auto" alt="Logo" />
                                </Link>
                            </div>

                            <div className="hidden sm:ml-6 sm:block">
                                <div className="flex space-x-4">
                                    {navMenu.map((data, i) => (
                                        <Link href={data.href} key={i} className={linkclass} aria-current="page">{data.title}</Link>
                                    ))}
                                    {isLogin &&
                                        <Link href={isLogin && "/addproducts"} className={linkclass} aria-current="page">Add new product</Link>
                                    }
                                </div>
                            </div>
                        </div>

                        {/* User Menu Button */}
                        <div className="absolute inset-y-0 right-0 flex items-center pr-2 sm:static sm:inset-auto sm:ml-6 sm:pr-0">
                            <div className="relative ml-3">
                                <div>
                                    {isLogin ? (
                                        <>
                                            <button
                                                type="button"
                                                className="relative flex rounded-full bg-gray-800 text-sm focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                                                id="user-menu-button"
                                                aria-expanded={isMenuOpen}
                                                aria-haspopup="true"
                                                onClick={toggleUserMenu}
                                            >
                                                <span className="sr-only">Open user menu</span>
                                                <Image
                                                    src={avatar}
                                                    className='rounded-full w-8'
                                                    alt='user logo'
                                                />
                                            </button>
                                        </>
                                    ) : (
                                        <div>
                                            <Link className={linkclass} href="/login">Login</Link>
                                            <Link className={linkclass} href="/register">Register</Link>
                                        </div>
                                    )}
                                </div>

                                {/* User Menu Dropdown */}
                                <div
                                    className={`absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none transition-all duration-300 ease-in-out ${isMenuOpen ? 'opacity-100 scale-100' : 'opacity-0 scale-95 pointer-events-none'}`}
                                    role="menu"
                                    aria-orientation="vertical"
                                    aria-labelledby="user-menu-button"
                                    tabIndex="-1"
                                >
                                    <Link href="#" className="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabIndex="-1">Your Profile</Link>
                                    <Link href="#" className="block px-4 py-2 text-sm text-gray-700" role="menuitem" tabIndex="-1">Settings</Link>
                                    <div className='flex justify-center my-2'>
                                        <button onClick={Logout} className="block bg-red-700 w-4/5 text-white mx-2 rounded-lg px-4 py-2 text-sm">Sign out</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Mobile Menu */}
                <div className={`sm:hidden transition-all duration-300 ease-in-out ${isMobileMenuOpen ? 'block opacity-100 scale-100' : 'hidden opacity-0 scale-95'}`} id="mobile-menu">
                    <div className="space-y-1 px-2 pb-3 pt-2">
                        {navMenu.map((data, i) => (
                            <a
                                href={data.href}
                                key={i}
                                className="block rounded-md hover:bg-gray-900 px-3 py-2 text-base font-medium text-white"
                                aria-current="page"
                            >
                                {data.title}
                            </a>
                        ))}
                    </div>
                </div>
            </nav>
        </div>
    );
};

export default Topbar;
