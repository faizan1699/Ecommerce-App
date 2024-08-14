"use client";

import React from 'react';
import Slider from 'react-slick';
import Image from 'next/image';

import img1 from "../../../assets/imgs/sliders/1.png";
import img2 from "../../../assets/imgs/sliders/2.jpg";
import img3 from "../../../assets/imgs/sliders/3.jpg";
import img4 from "../../../assets/imgs/sliders/4.jpg";

const images = [img1, img2, img3, img4];

const HomeSlider = () => {
    const settings = {
        rtl: true,
        speed: 1500,
        arrows: false,
        autoplay: true,
        infinite: true,
        slidesToShow: 3,
        slidesToScroll: 1,
        pauseOnHover: true,
        autoplaySpeed: 2000,
        centerMode: true,
        centerPadding: "0px",
        responsive: [
            {
                breakpoint: 1400, 
                settings: {
                    slidesToShow: 3,
                    centerPadding: "40px",
                },
            },
            {
                breakpoint: 800, 
                settings: {
                    slidesToShow: 2,
                    centerPadding: "40px",
                },
            },
            {
                breakpoint: 500, 
                settings: {
                    slidesToShow: 1,
                    centerPadding: "20px",
                },
            },
        ],
    };

    return (
        <Slider {...settings}>
            {images.map((imgs, index) => (
                <div
                    key={index}
                    className='flex justify-center items-center mx-4 lg:mx-4 md:mx-4'
                    style={{ minWidth: 360, margin: `0` }}
                >
                    <Image
                        src={imgs}
                        width={300}
                        height={300}
                        style={{ maxHeight: 360, minHeight: 360, minWidth: 360 }}
                        className='object-cover'
                        alt={`Image ${index + 1}`}
                    />
                </div>
            ))}
        </Slider>
    );
};

export default HomeSlider;
