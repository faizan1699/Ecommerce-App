"use client";

import React, { useEffect } from "react";

import Topbar from "../topbar/page";
import "slick-carousel/slick/slick.css";
import "slick-carousel/slick/slick-theme.css";

const Root = () => {
  useEffect(() => {
    const numberInputs = document.getElementsByTagName("input");

    const handleKeyDown = (event) => {
      if (event.key === "ArrowUp" || event.key === "ArrowDown") {
        event.preventDefault();
      }
    };

    const handleWheel = (event) => {
      event.preventDefault();
    };
    Array.from(numberInputs).forEach((input) => {
      input.addEventListener("keydown", handleKeyDown);
      input.addEventListener("wheel", handleWheel);
    });
    return () => {
      Array.from(numberInputs).forEach((input) => {
        input.removeEventListener("keydown", handleKeyDown);
        input.removeEventListener("wheel", handleWheel);
      });
    };
  }, []);

  return (
    <div>
      <Topbar />
    </div>
  );
};

export default Root;
