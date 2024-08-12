import React from 'react';

import ContactForm from './form/form';

const listItems = [
  { title: "Order Support : ", text: "Issues with your recent purchase or need help with tracking?" },
  { title: "Product Information : ", text: "Questions about our products or need recommendations?" },
  { title: "Account Assistance : ", text: "Help with your account settings or login issues?" },
  { title: "General Inquiries : ", text: "Any other questions or feedback?" }
];


const Contact = () => {
  return (

    <div className="grid grid-cols-1 gap-4 px-2 mx-auto py-5 w-full lg:w-3/4 md:w-3/4">

      <div className="border-gray-800 border-8 py-4 px-4 rounded w-full">

        <div className="text-center text-gray-800 text-3xl font-black mt-12 mb-16">Get in Touch with Us!</div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">

          <div className="col-span-12 lg:col-span-7">


            <p className='font-black text-2xl mb-12'>How can we assist you today?</p>

            <p className='my-3 font-bold mb-12'>We're here for your questions, order support, product inquiries, or feedback. Our team is ready!</p>

            <div className="my-3">

              {listItems.map((text, i) => (
                <li key={i} className='mt-3 text-justify pl-3 ml-4'>
                  <span className='font-black'>{text.title}</span>
                  <span className=''>{text.text}</span>
                </li>
              ))}

              <p className='my-12'>We strive to respond to all inquiries within 24 hours. Thank you for reaching out, and we look forward to assisting you!</p>
            </div>

          </div>


          <div className="col-span-12 lg:col-span-5">
            <ContactForm />
          </div>


        </div>

      </div>

    </div>

  )
}

export default Contact