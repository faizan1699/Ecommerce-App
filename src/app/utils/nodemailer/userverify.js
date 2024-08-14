import { DateTime } from "luxon";
import nodemailer from "nodemailer";
import { NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import User from "../Models/usersmodal";

const pass = process.env.PASS;
const admin = process.env.USER;
const DOMAIN = process.env.DOMAIN;

export async function SendVerifyEmail(email) {
  try {
    console.log("Sending email to:", email);

    const hashedToken = await bcrypt.hash(email.toString(), 10);
    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const tokenExpiry = DateTime.now().setZone(userTimezone).plus({ hours: 2 });

    const updateFields = {
      emailverifytoken: hashedToken,
      emailverifytokenexp: tokenExpiry.toJSDate(),
    };

    const user = await User.findOne({ email });

    if (!user) {
      return NextResponse.json(
        { message: "User not found with this email" },
        { status: 404 }
      );
    }

    Object.assign(user, updateFields);
    await user.save();

    const transporter = nodemailer.createTransport({
      service: "gmail",
      auth: {
        user: admin,
        pass: pass,
      },
    });

    const mailOptions = {
      from: admin,
      to: email,
      subject: "Verify Your Email",
      html: `
       <table border="0" cellpadding="0" cellspacing="0" style="border: 2px solid #02187e; max-width: 600px; margin: auto; border-radius: 25px;">
    <tr>
        <td style="background: #01125e; padding: 10px 0; border-top-left-radius: 20px; border-top-right-radius: 20px;">
            <h4 style="text-align: center; font-size: 25px; color: aliceblue;">Verify Your Email</h4>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px 10px; text-align: center;">
            <a href="${DOMAIN}/verifyemail?token=${hashedToken}" 
                style="border: 5px solid red; font-weight: bold; background-color: rgb(235, 0, 0); color: rgb(178, 245, 236); text-decoration: none; padding: 10px; width: 300px; border-radius: 10px; display: inline-block;">
                Click here to verify your email
            </a>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px; font-family: Arial, sans-serif; font-size: 20px; line-height: 1.5; color: #630000;">
            This is a one-time email verification link. If you click this link, it will expire after some time.
            To verify your email, copy and paste the link below into your browser.
            <br><br>
            <span style="font-size: 14px; color: #0b0b92e8;">Copy link: ${DOMAIN}/verifyemail?token=${hashedToken}</span>
        </td>
    </tr>
    <tr>
        <td style="padding: 40px; background: #01125e; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;">
        </td>
    </tr>
</table>

      `,
    };

    await transporter.sendMail(mailOptions);
    console.log("Email sent successfully");
  } catch (error) {
    console.error("Error sending email:", error);
    return NextResponse.json({ message: error?.message }, { status: 500 });
  }
}
