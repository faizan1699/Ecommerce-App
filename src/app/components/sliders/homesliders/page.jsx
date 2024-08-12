"use client";

import React from 'react';
import Slider from 'react-slick';
import Image from 'next/image';

import img1 from "../../../assets/imgs/sliders/1.png";
import img2 from "../../../assets/imgs/sliders/2.jpg";
import img3 from "../../../assets/imgs/sliders/3.jpg";
import img4 from "../../../assets/imgs/sliders/4.jpg";

// Array of images
const images = [img1, img2, img3, img4];

const HomeSlider = () => {
    const settings = {
        rtl: true,
        speed: 1100,
        arrows: false,
        autoplay: true,
        infinite: true,
        slidesToShow: 3,
        autoslide: true,
        centerMode: true,
        slidesToScroll: 1,
        pauseOnHover: true,
        autoplaySpeed: 2000,
        centerPadding: "60px",
    };

    return (
   
            <Slider {...settings}>
                {images.map((img, index) => (
                    <div key={index} className='flex justify-center items-center mx-auto ' style={{ minWidth: 250 }}>
                        <Image
                            src={img}
                            width={300}
                            height={300}
                            style={{ maxHeight: 300, minHeight: 300, minWidth: 300 }}
                            className='object-cover px-4'
                            alt={`Image ${index + 1}`}
                        />
                    </div>
                ))}
            </Slider>
      
    );
};

export default HomeSlider;
