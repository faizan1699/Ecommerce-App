

import connect from "@/app/utils/dbconfig/dbConfig";
import User from "@/app/utils/Models/usersmodal";
import nodemailer from "nodemailer";
import { DateTime } from "luxon";
import bcrypt from "bcrypt";
import { NextResponse } from "next/server";

connect();

const Admin = process.env.USER;
const pass = process.env.PASS;
const DOMAIN = process.env.DOMAIN;

export const sendMailVerification = async (email) => {
  try {
    const user = await User.findOne({ email });

    if (!user) {
      return NextResponse.json({ message: "user not found" }, { status: 404 });
    }

    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const tokenExpiry = DateTime.now().setZone(userTimezone).plus({ hours: 2 });

    const hashedtoken = await bcrypt.hash(email.toString(), 10);

    const updatefields = {
      emailverifytoken: hashedtoken,
      emailverifytokenexp: tokenExpiry.toJSDate(),
    };

    Object.assign(user, updatefields);
    await user.save();

    const transporter = nodemailer.createTransport({
      service: "gmail",
      auth: {
        user: Admin,
        pass: pass,
      },
    });

    const mailOption = {
      from: Admin,
      to: email,
      subject: "Verify your Email",
      html: `
      <div style="border: 2px solid #02187e; max-width: 600px; margin: auto; border-radius: 25px;">
        <div style="padding: 10px 0; background: #01125e; border-top-left-radius: 20px;border-top-right-radius: 20px;">
            <h4 style="text-align: center; font-size: 25px; color: aliceblue;">verify your email</h4>
        </div>
        <div style="display: flex; justify-content: center;">

            <div style="margin: 20px 0; padding: 20px 10px;">

                <div style="display:flex; flex-direction: column; justify-content-center;">
                    <div style="margin: auto;">
                        <a style="border: 5px solid red; font-weight: bold; background-color: rgb(235, 0, 0); color: rgb(178, 245, 236); text-decoration: none; padding: 10px; width: 300px; border-radius: 10px;"
                            href="${DOMAIN}/verifyemail?token=${hashedtoken}">
                            Click here to verify email
                        </a>
                    </div>

                    <p style="font-family: Arial, sans-serif; font-size: 20px; line-height: 1.5; color: #630000;">
                        this is one time email verification link , if you click this this will be expires after some
                        time To verify your email, copy and paste the link below in your browser.
                        <br>
                        <span style="font-size: 14px; padding: 0 auto; color: #0b0b92e8;">copy link :
                            ${DOMAIN}/verifyemail?token=${hashedtoken}</span>
                    </p>

                </div>
            </div>
        </div>
        <div
            style="padding: 40px; background: #01125e; border-bottom-left-radius: 20px;border-bottom-right-radius: 20px;">
        </div>
    </div>
      `,
    };

    await transporter.sendMail(mailOption);
    console.log("Mail sent successfully");
  } catch (error) {
    console.error("Error sending verification email:", error);
  }
};
