import { NextResponse } from "next/server";

const secret = process.env.JWT_SECRET;

export async function POST(req) {
  try {
    const cookies = req.cookies;
    const token = cookies.get("jwt");

    if (!token) {
      return NextResponse.json(
        { message: "token not provided" },
        { status: 401 }
      );
    }

    // verify jwt

    const decoded = jwt.verify(token, secret);
    return NextResponse.json({
      islogedIn: true,
      success: true,
      user: decoded,
    });
  } catch (error) {
    if (error?.name === "JsonWebTokenError") {
      return NextResponse.json({ message: "Invalid token" }, { status: 401 });
    } else if (error?.name === "TokenExpiredError") {
      return NextResponse.json({ message: "Token expired" }, { status: 401 });
    } else {
      return NextResponse.json({ message: error?.message }, { status: 500 });
    }
  }
}
