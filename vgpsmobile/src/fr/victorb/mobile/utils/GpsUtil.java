/*
License: GNU General Public License

This file is part of VisuGps

VisuGps is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

VisuGps is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with VisuGps; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Copyright (c) 2008 Victor Berchet, <http://www.victorb.fr>
*/

package fr.victorb.mobile.utils;

import fr.victorb.mobile.vgps.Constant;
import fr.victorb.mobile.vgps.controller.Controller;
import java.io.DataOutputStream;
import java.io.IOException;
import java.util.Random;
import javax.microedition.io.Connector;
import javax.microedition.io.HttpConnection;

public class GpsUtil {
    public static boolean hasInternalGps() {
        if (System.getProperty("microedition.location.version") != null) {
            return true;
        } else {
            return false;
        }
    }   
    
    public static void requestNetworkPermission() {
        new Thread(new Helper(false)).start();
    }

    public static void testDataTransfer() {
        new Thread(new Helper(true)).start();
    }    
    
    private static class Helper implements Runnable {
        private byte[] data;
        private Random random = new Random();
        private int rand;
        private boolean status = false;
        private boolean test;
        
        public Helper(boolean test) {
            this.test = test;
            if (test) {
                random.setSeed(System.currentTimeMillis());
                rand = random.nextInt(1000);
                data = new String("test=1&id=" + String.valueOf(rand)).getBytes();
            } else {
                data = new String("perm=1").getBytes();
            }
        }
       
        public void run() {
            DataOutputStream stream = null;
            HttpConnection connection = null;
            try {
//#if Blackberry_NO_MDS
//#                 connection = (HttpConnection)Connector.open(Constant.LOGURL + ";deviceside=true", Connector.READ_WRITE);
//#else
                connection = (HttpConnection)Connector.open(Constant.LOGURL, Connector.WRITE);
//#endif
                connection.setRequestMethod(HttpConnection.POST);
                connection.setRequestProperty("Content-Type","application/x-www-form-urlencoded");
                connection.setRequestProperty("Content-Length", Integer.toString(data.length));
                stream = connection.openDataOutputStream();            
                stream.write(data, 0, data.length);
                stream.close();
                status = (connection.getResponseCode() == 200);
            } catch (IOException e) {            
            } finally {
                if (connection != null) {
                    try {
                        connection.close();
                    } catch (Exception e) {
                    }
                }
                try {
                    stream.close();
                } catch (Exception e) {
                }
                if (test) {
                    Controller.getController().alert("Data transfer", 
                                                      status?"Connection successful \nID=" + String.valueOf(rand):"Connection error!");
                }
            }
        }        
    }    
}
