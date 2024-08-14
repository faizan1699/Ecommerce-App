import { NextResponse } from "next/server";
import User from "@/app/utils/Models/usersmodal";
import bcrypt from "bcryptjs";
import jwt from "jsonwebtoken";
import connect from "@/app/utils/dbconfig/dbConfig";

const jwtsecret = process.env.JWT_SECRET;

connect();

export async function POST(req) {
  try {
    const reqbody = await req.json();
    const { email, password } = reqbody;

    if (!email) {
      return NextResponse.json(
        { message: "Email are required" },
        { status: 400 }
      );
    }
    if (!password) {
      return NextResponse.json(
        { message: "Password are required" },
        { status: 400 }
      );
    }

    const user = await User.findOne({ email });

    if (!user) {
      return NextResponse.json({ message: "User not found" }, { status: 404 });
    }

    const isverified = await user.emailverify;
  
    if (!isverified) {
      return NextResponse.json({ message: "pls verify your email first" }, { status: 400 });
    }

    const checkPassword = await bcrypt.compare(password, user.password); // Fixed the password comparison

    if (!checkPassword) {
      return NextResponse.json(
        { message: "Invalid password" },
        { status: 401 }
      );
    }

    const tokenData = {
      name: user.name,
      email: user.email,
      isadmin: user.isadmin,
      verified: user.emailverify,
      createdAt: user.createdAt,
    };

    const token = jwt.sign(tokenData, jwtsecret);

    const response = NextResponse.json(
      {
        success: true,
        message: "Login successful",
      },
      { status: 200 }
    );

    response.cookies.set("ua", true); // for check user login or not
    response.cookies.set("jwt", token, {
      httpOnly: true,
      maxAge: 10 * 24 * 60 * 60, // This is 10 days, not 50 days
    });

    return response;
  } catch (error) {
    console.error("Error during authentication:", error);
    return NextResponse.json(
      { message: "Internal server error" },
      { status: 500 }
    );
  }
}
