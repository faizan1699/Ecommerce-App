import { NextResponse } from "next/server";

import User from "@/app/utils/Models/usersmodal";
import bcrypt from "bcryptjs";

import connect from "@/app/utils/dbconfig/dbConfig";

import { SendVerifyEmail } from "@/app/utils/nodemailer/userverify";

connect();

export async function POST(req) {
  try {
    const reqbody = await req.json();
    const { name, email, password } = reqbody;

    if (!name) {
      return NextResponse.json(
        {
          message: "name required",
        },
        { status: 400 }
      );
    }
    if (!email) {
      return NextResponse.json(
        {
          message: "email required",
        },
        { status: 400 }
      );
    }
    if (!password) {
      return NextResponse.json(
        {
          message: "password required",
        },
        { status: 400 }
      );
    }

    const user = await User.findOne({ email });

    if (user) {
      return NextResponse.json(
        { message: "email already taken" },
        { status: 400 }
      );
    }

    const salt = await bcrypt.genSalt(10);
    const hashedPassword = await bcrypt.hash(password, salt);

    const newUser = new User({
      name,
      email,
      password: hashedPassword,
    });

    const saveduser = await newUser.save();

    SendVerifyEmail(email);

    const response = NextResponse.json({
      message:
        "user registerd successfully & an verification email was sent to your account",
      success: true,
      saveduser,
    });

    return response;
  } catch (error) {
    return NextResponse.json({ error: error?.message }, { status: 500 });
    console.log(error.message);
  }
}
