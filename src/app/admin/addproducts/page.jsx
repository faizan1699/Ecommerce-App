"use client";

import { showAlert } from '@/app/components/alert/alert';
import React, { useEffect, useRef, useState } from 'react';
import Loader from '../../assets/loader/loader.gif';
import axios from 'axios';
import Image from 'next/image';

const ProductUpload = () => {

    const uploadImgs = useRef(null);
    const handleSubmitref = useRef(null);

    const labelClass = "block text-sm font-medium text-white mb-1";
    const inputClass = "block w-full border-gray-300 outline-none rounded-md shadow-sm sm:text-sm px-3 py-3";
    const btnClass = "w-full px-4 py-3 bg-blue-900 text-white font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500";

    const [categories, setCategories] = useState([]);
    const [subcategories, setSubcategories] = useState([]);
    const [loading, setLoading] = useState(false);
    const [imgs, setImages] = useState([]);
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        price: '',
        discountedPrice: '',
        category: "",
        subcategory: "",
    });

    const handleUploadImg = () => {
        uploadImgs.current.click();
    }

    useEffect(() => {
        getMainCategory();
    }, []);

    const getMainCategory = async () => {
        try {
            const response = await axios.get('/api/products/categories/main');
            setCategories(response?.data?.category);
        } catch (error) {
            console.log('Error fetching categories:', error?.response?.data?.message || error.message);
        }
    }

    const getSubCategory = async (categoryId) => {
        try {
            const response = await axios.get(`/api/products/categories/sub?id=${categoryId}`);
            // const response = await axios.get(`/api/products/categories/sub/${categoryId}`);
            console.log('Subcategories API Response:', response.data);
            setSubcategories(response?.data?.subcategories || []);
        } catch (error) {
            console.log('Error fetching subcategories:', error?.response?.data?.message || error.message);
        }
    }

    const handleImageChange = (e) => {
        const files = Array.from(e.target.files);
        const validImageTypes = ['image/jpeg', 'image/png'];

        const newImagePromises = files
            .filter(file => validImageTypes.includes(file.type))
            .map(file => {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onloadend = () => {
                        resolve(reader.result); // Base64 string
                    };
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            });

        Promise.all(newImagePromises)
            .then(base64Images => {
                setImages(prevImages => [...prevImages, ...base64Images]);
            })
            .catch(error => {
                showAlert('error', 'Error', 'Failed to process images');
            });
    };

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prevData => ({
            ...prevData,
            [name]: value
        }));
    };

    const handleCategoryChange = (e) => {
        const selectedCategoryId = parseInt(e.target.value, 10);
        setFormData(prevData => ({
            ...prevData,
            category: selectedCategoryId,
            subcategory: ""
        }));
        getSubCategory(selectedCategoryId);
    };

    const handleSubcategoryChange = (e) => {
        setFormData(prevData => ({
            ...prevData,
            subcategory: parseInt(e.target.value, 10)
        }));
    };

    const handleImageRemove = (index) => {
        setImages(prevImages => prevImages.filter((_, i) => i !== index));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
          const data = {
            imgs: imgs,
            name: formData.name,
            price: formData.price,
            category: formData.category,
            subcategory: formData.subcategory,
            description: formData.description,
            discountedprice: formData.discountedPrice,
        };

        try {
            const response = await axios.post("/api/products/add", data);
            showAlert("success", "Success", response?.data?.message);
            setLoading(false);
            setImages([]);
            setFormData({
                name: '',
                description: '',
                price: '',
                discountedPrice: '',
                category: "",
                subcategory: "",
            });
          } catch (error) {
            showAlert('error', 'Error', error?.response?.data?.message, 4000);
            setLoading(false);
             }
    };

    return (

        <div>
            <h2 className="text-3xl font-bold text-center my-6 text-gray-600">Upload Product Details</h2>
            <div className="max-w-7xl mx-auto p-6 bg-gray-900 shadow-lg rounded-lg border border-gray-200 my-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="imgs" className={labelClass}>Product Images <span className='text-red-600'>only jpeg & png imgs</span></label>
                            <input
                                type="file"
                                id="imgs"
                                accept="image/jpeg, image/png"
                                onChange={handleImageChange}
                                ref={uploadImgs}
                                multiple
                                className="hidden"
                            />
                            <button type="button" onClick={handleUploadImg} className='w-full bg-red-400 py-2 rounded-md text-white'>Upload Images</button>

                            {imgs?.length > 0 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {imgs?.map((imgSrc, index) => (
                                        <div key={index} className="relative">
                                            <img
                                                src={imgSrc}
                                                alt={`Product Preview ${index}`}
                                                className="w-24 h-24 object-cover rounded-md border border-gray-300"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => handleImageRemove(index)}
                                                className="absolute top-0 right-0 bg-red-500 text-white rounded-full px-1 m-1"
                                                aria-label="Remove image"
                                            >
                                                &times;
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div>
                            <label htmlFor="name" className={labelClass}>Product Name</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value={formData.name}
                                onChange={handleInputChange}
                                className={`${inputClass} w-full`}
                                required
                            />
                        </div>

                        <div>
                            <label htmlFor="description" className={labelClass}>Description</label>
                            <textarea
                                id="description"
                                name="description"
                                value={formData.description}
                                onChange={handleInputChange}
                                rows="2"
                                className={`${inputClass} w-full`}
                                required
                            ></textarea>
                        </div>

                        <div>
                            <label htmlFor="price" className={labelClass}>Actual Price ($)</label>
                            <input
                                type="number"
                                id="price"
                                name="price"
                                value={formData.price}
                                onChange={handleInputChange}
                                className={`${inputClass} w-full`}
                                required
                            />
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div>
                            <label htmlFor="discountedPrice" className={labelClass}>Discounted Price ($)</label>
                            <input
                                type="number"
                                id="discountedPrice"
                                name="discountedPrice"
                                value={formData.discountedPrice}
                                onChange={handleInputChange}
                                className={`${inputClass} w-full`}
                            />
                        </div>

                        <div>
                            <label htmlFor="category" className={labelClass}>Category</label>
                            <select
                                className={`${inputClass} w-full`}
                                onChange={handleCategoryChange}
                                value={formData.category}
                                name="category"
                                id="category"
                            >
                                <option value="">Select an option</option>
                                {categories?.length > 0 ? (
                                    categories.map(cat => (
                                        <option key={cat.id} value={cat.id}>
                                            {cat.name}
                                        </option>
                                    ))
                                ) : (
                                    <option value="">No categories available</option>
                                )}
                            </select>
                        </div>

                        <div>
                            <label htmlFor="subcategory" className={labelClass}>Sub Category</label>
                            <select
                                id="subcategory"
                                name="subcategory"
                                value={formData.subcategory}
                                onChange={handleSubcategoryChange}
                                className={`${inputClass} w-full`}
                                disabled={!formData.category}
                            >
                                <option value="">Select a subcategory</option>
                                {subcategories?.length > 0 ? (
                                    subcategories.map(sub => (
                                        <option key={sub.id} value={sub.id}>
                                            {sub.name}
                                        </option>
                                    ))
                                ) : (
                                    <option value="" disabled>No subcategories available</option>
                                )}
                            </select>
                        </div>

                        <div>
                            <button className='hide' ref={handleSubmitref} type='submit'>hide btn</button>
                            <button type="submit" className={btnClass}>
                                <span className="flex justify-center">
                                    {loading ? <Image src={Loader} alt="Loading..." width={25} height={20} /> : 'Submit'}
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    );
};

export default ProductUpload;
