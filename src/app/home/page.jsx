import React from 'react';

import Products from '../products/page';
import HomeSlider from '../components/sliders/homesliders/page';

const Home = () => {
    return (

        <div className='lg:px-4 bg-gray-200 py-8'>

            <div className="px-3 lg:w-3/4 md:w-3/4 w-full mx-auto ">
                <HomeSlider />
            </div>

            <div className="" style={{ height: 'auto', maxHeight: '600px', overflowY: "scroll" }}>
                <Products />
            </div>
        </div>

    )
}

export default Home