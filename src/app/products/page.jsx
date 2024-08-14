"use client";

import React, { useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { showAlert } from '../components/alert/alert';

import Image from 'next/image';
import axios from 'axios';
import Slider from 'react-slick';
import 'slick-carousel/slick/slick.css';
import 'slick-carousel/slick/slick-theme.css';
import { faCartShopping } from '@fortawesome/free-solid-svg-icons';
import Loader from "../assets/loader/loader.gif";

const Products = () => {


    const [pageload, setPageload] = useState(false);
    const [product, setProducts] = useState([]); // Initialize as an empty array

    useEffect(() => {
        GetProducts();
    }, []);

    const GetProducts = async () => {
        setPageload(true);
        try {
            const response = await axios.get('/api/products/get');
            setProducts(response?.data?.products || []);
            setPageload(false);
        } catch (error) {
            setPageload(false);
            console.error('API Error:', error); // Log the error details
            showAlert('error', 'Error', error?.response?.data?.message || 'An unknown error occurred');
        }
    };

    const reloadpage = () => {
        GetProducts();
    };

    const sliderSettings = {
        dots: true,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplaySpeed: 4000,
        pauseOnHover: true,
        autoplay: true,
        swipeToSlide: true,
        arrows: false,
    };


    return (
        <div className="p-8  bg-gray-600">

            <div className='flex justify-end'>
                <button onClick={reloadpage} className="mb-4 px-4 py-2 bg-red-400 text-white rounded hover:bg-blue-600 transition">Reload</button>
            </div>
            {pageload ?
                (
                    <div className='flex justify-center items-center'>
                        <Image
                            src={Loader}
                            width={100}
                            height={100}
                            style={{ maxHeight: 100 }}
                            alt="loading"
                        />
                    </div>
                ) : (
                    <div>
                        {product && product.length === 0 ?
                            <div className='text-red-500 text-center font-2xl'>Product Not Found Yet</div>
                            : <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6" >
                                {Array.isArray(product) && product.map((item, i) => (
                                    <div key={i} className="flex flex-col bg-white border border-black rounded-lg shadow-lg transition-transform transform hover:scale-105 hover:shadow-xl mx-auto" style={{ minWidth: "330px", maxWidth: "350px" }}>
                                        <div className="relative overflow-hidden rounded-t-lg">
                                            {item.imgs && item.imgs.length > 0 && (
                                                <Slider {...sliderSettings} className="relative">
                                                    {item.imgs.map((imgSrc, j) => (
                                                        <div key={j} className="relative">
                                                            <Image
                                                                key={j}
                                                                className="w-full h-64 object-cover border rounded"
                                                                src={imgSrc}
                                                                width={320}
                                                                height={250}
                                                                layout="responsive"
                                                                style={{ maxHeight: "250px", minHeight: 320 }}
                                                                alt={`Product Image ${j}`}
                                                            />
                                                        </div>
                                                    ))}
                                                </Slider>
                                            )}
                                        </div>

                                        <div className="p-4 flex flex-col flex-grow justify-between">

                                            <div className="flex flex-col justify-start">
                                                <h5 className="text-xl font-black text-blue-900 dark:text-white mb-2 ">{item.name}</h5>
                                                <p className="text-gray-700 dark:text-gray-400 mb-2 ">{item.description}</p>
                                            </div>

                                            <div className="flex justify-end items-center justify-between mt-4">
                                                <span className={`text-lg font-bold text-gray-900 ${item.discountedprice ? "line-through text-gray-300" : ""}`}>${item.price}</span>
                                                {item.discountedprice && <span className="text-lg font-bold text-blue-900 dark:text-white">${item.discountedprice}</span>}
                                                <button className="px-4 py-2 bg-red-400 text-white rounded hover:bg-green-600 transition">
                                                    <FontAwesomeIcon icon={faCartShopping} /> Add to Cart</button>
                                            </div>

                                        </div>

                                    </div>
                                ))}
                            </div>
                        }
                    </div>
                )}

        </div >

    );
}

export default Products;
