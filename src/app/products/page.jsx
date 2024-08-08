"use client";

import React, { useEffect, useState } from 'react';
import Image from 'next/image';
import axios from 'axios';
import { showAlert } from '../components/alert/alert';
import Slider from 'react-slick';
import 'slick-carousel/slick/slick.css';
import 'slick-carousel/slick/slick-theme.css';

const Products = () => {

    const [product, setProducts] = useState([]);
    useEffect(() => {
        GetProducts();
    }, []);

    const reloadpage = () => {
        GetProducts();
    }
    

    const GetProducts = async () => {
        try {
            const response = await axios.get('/api/products/get');
            // console.log(response);
            setProducts(response?.data?.data?.products || []);
        } catch (error) {
            showAlert('error', 'Error', error?.response?.data?.message);
        }
    }

    const sliderSettings = {
        dots: true,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplaySpeed: 5000,
        pauseOnHover: true,
        autoplay: true,
        swipeToSlide: true,
        arrows: false,
    };

    return (
        <div className="px-8 bg-gray-600">
            <button onClick={reloadpage} className="mb-4 px-4 py-2 bg-blue-500 text-white rounded">Reload</button>
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-5">
                {Array.isArray(product)  && product?.map((item, i) => (
                    <div key={i} className="flex justify-between flex-col bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
                        <div className="relative">
                            {item.imgs && item.imgs.length > 0 && (
                                <Slider {...sliderSettings} className="relative">
                                    {item.imgs.map((imgSrc, j) => (
                                        <div key={j} className="relative mb-4">
                                            <Image
                                                className="rounded-t-lg"
                                                src={imgSrc}
                                                alt={`Product Image ${j}`}
                                                width={220}
                                                height={220}
                                                layout="responsive"
                                                style={{ maxHeight: "250px" }}
                                            />
                                        </div>
                                    ))}
                                </Slider>
                            )}
                        </div>
                        <div className="p-5 mt-8">
                            <h5 className="mb-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{item.name}</h5>
                            <p className="mb-3 font-normal text-gray-700 dark:text-gray-400">{item.description}</p>
                        </div>
                    </div>
                ))}
            </div>

        </div>
    );
}

export default Products;
