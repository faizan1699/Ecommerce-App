import { NextResponse } from "next/server";

import User from "@/app/utils/Models/usersmodal";
import bcrypt from "bcryptjs";

import connect from "@/app/utils/dbconfig/dbConfig";

connect();

export async function POST(req) {
  try {
    const reqbody = await req.json();
    const { name, email, password } = reqbody;

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

    const response = NextResponse.json({
      message: "user registerd successfully",
      success: true,
      saveduser,
    });

    return response;
  } catch (error) {
    return NextResponse.json({ error: error?.message }, { status: 500 });
    console.log(error.message);
  }
}
