"use client"

import axios from 'axios';
import Link from 'next/link';
import Image from 'next/image';
import Loader from "../assets/loader/loader.gif";

import { useContext, useState } from 'react';
import { useRouter } from 'next/navigation';
import { showAlert } from '../components/alert/alert';
import { IfUserLoginContext } from '../components/context/page';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEye, faEyeSlash } from '@fortawesome/free-solid-svg-icons';

const Login = () => {

    const router = useRouter();

    const { setisLogin } = useContext(IfUserLoginContext);

    const [loading, setLoading] = useState(false);
    const [showpassword, SetShowPassword] = useState(true);
    const [formData, setFormData] = useState({
        email: '',
        password: '',
    });

    const handleChange = (e) => {
        setFormData({
            ...formData,
            [e.target.name]: e.target.value,
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            const response = await axios.post("/api/auth/login", formData);

            showAlert('success', 'Success', response?.data?.message, 5000);
            setLoading(false);
            router.push("/");
            setisLogin(true);
        }
        catch (error) {
            if (error?.response?.status === 400) {
                showAlert('info', 'Info', error?.response?.data?.message, 5000);
            } else {
                showAlert('error', 'Error', error?.response?.data?.message, 5000);
            }
            setLoading(false);
        }

    };

    return (
        <div className="flex items-center justify-center h-screen bg-gray-100">
            <div className="w-full max-w-sm p-8 bg-white shadow-2xl shadow-gray-400 rounded-lg">

                <h2 className="text-2xl font-bold mb-6 text-center">Login</h2>

                <form method="POST" onSubmit={handleSubmit}>


                    <div className="mb-4">
                        <label htmlFor="email" className="block text-sm font-medium text-gray-700">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value={formData.email}
                            onChange={handleChange}
                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            required
                        />
                    </div>

                    <div>
                        <label htmlFor="password" className="block text-sm font-medium text-gray-700">Password</label>
                        <div className="mb-4 flex border rounded-lg items-center">
                            <input
                                type={showpassword === true ? "password" : "text"}
                                id="password"
                                name="password"
                                value={formData.password}
                                onChange={handleChange}
                                className="mt-1 border-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                required
                            />
                            <span className='flex items-center mr-2'>
                                <FontAwesomeIcon onClick={() => SetShowPassword(!showpassword)} icon={showpassword === true ? faEye : faEyeSlash} className='text-gray-900' />
                            </span>
                        </div>
                    </div>

                    <button type="submit" className="w-full mt-4 py-2 px-4 bg-indigo-600 text-white font-semibold rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        {loading ?
                            <span className='flex justify-center items-center'>
                                <Image
                                    width="25"
                                    src={Loader}
                                    alt="loading"
                                />
                            </span>
                            : "Login"}
                    </button>
                </form>
                <div className='text-sm flex justify-end mt-2'> Not have account yet
                    <Link className='ml-2 text-red-800' href="/register">Register</Link>
                </div>

            </div>
        </div>
    );
};

export default Login;
